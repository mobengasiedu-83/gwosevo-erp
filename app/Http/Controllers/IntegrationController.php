<?php

namespace App\Http\Controllers;

use App\Domain\Administration\Modules;
use App\Domain\Identity\Entra;
use App\Models\PbxConnection;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Throwable;

class IntegrationController extends Controller
{
    private const PROVIDERS = ['vitalpbx', 'txtconnect', 'microsoft365', 'qdrant', 'ussd', 'custom'];
    private const API_ABILITIES = ['profile:read', 'leads:create', 'tickets:create', 'tickets:reply'];

    public function index(Request $request, Entra $entra)
    {
        $this->authorizeUser($request, 'integrations.view');
        $org = (int) $request->user()->active_organization_id;
        $providers = $this->providerStatus($entra, $org);

        return view('integrations.index', [
            'organizationId' => $org,
            'canManage' => $request->user()->isSuperAdmin() || $request->user()->can('integrations.manage'),
            'canManageTokens' => $request->user()->isSuperAdmin() || $request->user()->can('integrations.tokens.manage'),
            'canCheck' => $request->user()->isSuperAdmin() || $request->user()->can('integrations.health.check'),
            'providers' => $providers,
            'connections' => DB::table('integration_registry as i')->leftJoin('users as u', 'u.id', '=', 'i.owner_id')->where('i.organization_id', $org)->select('i.*', 'u.name as owner_name')->latest('i.updated_at')->get(),
            'users' => User::query()->where('status', 'active')->whereHas('organizations', fn ($q) => $q->where('organizations.id', $org))->orderBy('name')->get(['id', 'name', 'email']),
            'pbxProfiles' => PbxConnection::where('organization_id', $org)->where('driver', 'vitalpbx')->orderBy('name')->get(),
            'tokens' => $request->user()->tokens()->latest()->get(),
            'abilities' => self::API_ABILITIES,
            'logs' => DB::table('integration_health_logs')->where('organization_id', $org)->latest('checked_at')->paginate(20),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeUser($request, 'integrations.manage');
        $org = (int) $request->user()->active_organization_id;
        $data = $request->validate([
            'provider' => ['required', Rule::in(self::PROVIDERS)],
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:2000'],
            'base_url' => ['nullable', 'url:https', 'max:500'],
            'credential_source' => ['required', Rule::in(['environment', 'module_profile', 'external_manager'])],
            'owner_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('status', 'active')],
        ]);
        if (! empty($data['owner_id'])) abort_unless(User::findOrFail($data['owner_id'])->canAccessOrganization($org), 422, 'The integration owner must belong to this organization.');
        DB::table('integration_registry')->insert([...$data, 'organization_id' => $org, 'status' => 'active', 'created_by' => $request->user()->id, 'created_at' => now(), 'updated_at' => now()]);
        Log::channel('integrations')->info('Integration registry entry created.', ['organization_id' => $org, 'provider' => $data['provider'], 'name' => $data['name'], 'actor_id' => $request->user()->id]);
        return back()->with('success', 'Integration registry entry created. Secrets are not stored in this registry; maintain them in the listed environment or provider profile.');
    }

    public function status(Request $request, int $registry)
    {
        $this->authorizeUser($request, 'integrations.manage');
        $record = DB::table('integration_registry')->where('organization_id', $request->user()->active_organization_id)->where('id', $registry)->first();
        abort_unless($record, 404);
        $next = $record->status === 'active' ? 'inactive' : 'active';
        DB::table('integration_registry')->where('id', $registry)->update(['status' => $next, 'updated_at' => now()]);
        Log::channel('integrations')->info('Integration registry entry status changed.', ['organization_id' => $record->organization_id, 'provider' => $record->provider, 'registry_id' => $registry, 'status' => $next, 'actor_id' => $request->user()->id]);
        return back()->with('success', 'Registry entry marked '.$next.'. This does not change provider runtime configuration.');
    }

    public function tokenStore(Request $request)
    {
        $this->authorizeUser($request, 'integrations.tokens.manage');
        $data = $request->validate(['name' => ['required', 'string', 'max:100'], 'abilities' => ['required', 'array', 'min:1'], 'abilities.*' => ['required', Rule::in(self::API_ABILITIES)], 'expires_days' => ['required', 'integer', 'min:1', 'max:365']]);
        $token = $request->user()->createToken($data['name'], array_values(array_unique($data['abilities'])), now()->addDays($data['expires_days']));
        Log::channel('integrations')->notice('Scoped API token created.', ['user_id' => $request->user()->id, 'token_id' => $token->accessToken->id, 'abilities' => $token->accessToken->abilities, 'expires_at' => $token->accessToken->expires_at]);
        return back()->with('new_api_token', $token->plainTextToken)->with('success', 'API token created. Copy it now; it is shown only once.');
    }

    public function tokenRevoke(Request $request, int $token)
    {
        $this->authorizeUser($request, 'integrations.tokens.manage');
        $accessToken = $request->user()->tokens()->whereKey($token)->firstOrFail();
        $accessToken->delete();
        Log::channel('integrations')->notice('Scoped API token revoked.', ['user_id' => $request->user()->id, 'token_id' => $token]);
        return back()->with('success', 'Your API token was revoked.');
    }

    public function health(Request $request, Entra $entra)
    {
        $this->authorizeUser($request, 'integrations.health.check');
        $org = (int) $request->user()->active_organization_id;
        $data = $request->validate(['provider' => ['required', Rule::in(self::PROVIDERS)], 'registry_id' => ['nullable', 'integer'], 'pbx_profile_id' => ['nullable', 'integer']]);
        $registry = null;
        if (! empty($data['registry_id'])) {
            $registry = DB::table('integration_registry')->where('organization_id', $org)->where('id', $data['registry_id'])->first();
            abort_unless($registry && $registry->provider === $data['provider'], 404);
        }
        $correlationId = (string) Str::uuid();
        $started = microtime(true);
        $httpStatus = null;
        try {
            [$outcome, $summary, $httpStatus] = $this->probe($data['provider'], $data['pbx_profile_id'] ?? null, $org, $entra);
        } catch (Throwable $exception) {
            $outcome = 'unavailable';
            $summary = 'The connectivity check failed. Review the server log using correlation ID '.$correlationId.'.';
            Log::channel('integrations')->warning('Integration health probe failed.', ['provider' => $data['provider'], 'organization_id' => $org, 'correlation_id' => $correlationId, 'exception' => $exception::class]);
        }
        $latency = (int) round((microtime(true) - $started) * 1000);
        DB::table('integration_health_logs')->insert(['organization_id' => $org, 'registry_id' => $registry?->id, 'provider' => $data['provider'], 'correlation_id' => $correlationId, 'outcome' => $outcome, 'http_status' => $httpStatus, 'latency_ms' => $latency, 'summary' => $summary, 'checked_by' => $request->user()->id, 'checked_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        Log::channel('integrations')->info('Integration health probe completed.', ['provider' => $data['provider'], 'organization_id' => $org, 'correlation_id' => $correlationId, 'outcome' => $outcome, 'http_status' => $httpStatus, 'latency_ms' => $latency]);
        return back()->with($outcome === 'available' ? 'success' : 'warning', $summary.' Correlation ID: '.$correlationId);
    }

    private function probe(string $provider, ?int $pbxProfileId, int $org, Entra $entra): array
    {
        if ($provider === 'vitalpbx') {
            $base = (string) config('vitalpbx.base_url');
            $key = (string) config('vitalpbx.api_key');
            $tenant = config('vitalpbx.tenant');
            if ($pbxProfileId) {
                $profile = PbxConnection::where('organization_id', $org)->whereKey($pbxProfileId)->where('driver', 'vitalpbx')->firstOrFail();
                $base = $profile->base_url; $key = $profile->api_token; $tenant = $profile->settings['tenant'] ?? $tenant;
            }
            if (! $base || ! $key || parse_url($base, PHP_URL_SCHEME) !== 'https') return ['configuration_missing', 'Set a valid HTTPS VitalPBX endpoint and API key in .env or select a configured PBX profile.', null];
            $http = Http::acceptJson()->withHeader('app-key', $key)->connectTimeout(5)->timeout((int) config('vitalpbx.timeout'));
            if ($tenant) $http = $http->withHeader('tenant', $tenant);
            $response = $http->withOptions(['allow_redirects' => false])->get(rtrim($base, '/').'/api/v2/tenants');
            return $response->successful() ? ['available', 'VitalPBX API responded successfully over HTTPS.', $response->status()] : ['unavailable', 'VitalPBX returned HTTP '.$response->status().'. Check the API key, tenant and access rules.', $response->status()];
        }
        if ($provider === 'txtconnect') {
            $base = (string) config('services.txtconnect.base_url');
            if (! filled(config('services.txtconnect.key')) || ! filled(config('services.txtconnect.sender')) || parse_url($base, PHP_URL_SCHEME) !== 'https') return ['configuration_missing', 'TXTConnect HTTPS endpoint, API key and approved sender are not all configured.', null];
            $response = Http::acceptJson()->connectTimeout(5)->timeout((int) config('services.txtconnect.timeout'))->withOptions(['allow_redirects' => false])->get(rtrim($base, '/'));
            return $response->status() < 500 ? ['available', 'TXTConnect endpoint is reachable. The provider credentials and sender are not validated by this non-sending probe.', $response->status()] : ['unavailable', 'TXTConnect endpoint returned HTTP '.$response->status().'.', $response->status()];
        }
        if ($provider === 'microsoft365') {
            if (! $entra->ready()) return ['configuration_missing', 'Microsoft Entra is disabled or its tenant, application, secret or callback configuration is incomplete.', null];
            $tenant = config('identity.entra.tenant_id');
            $response = Http::acceptJson()->connectTimeout(5)->timeout(10)->withOptions(['allow_redirects' => false])->get('https://login.microsoftonline.com/'.$tenant.'/v2.0/.well-known/openid-configuration');
            return $response->successful() ? ['available', 'Microsoft identity discovery endpoint is reachable. This check does not perform sign-in or Graph access.', $response->status()] : ['unavailable', 'Microsoft identity discovery returned HTTP '.$response->status().'.', $response->status()];
        }
        if ($provider === 'qdrant') {
            $base = (string) config('knowledge.qdrant_url');
            if (! config('knowledge.semantic_enabled') || ! $base || parse_url($base, PHP_URL_SCHEME) !== 'https') return ['configuration_missing', 'Semantic search is disabled or the Qdrant HTTPS URL is not configured.', null];
            $http = Http::acceptJson()->connectTimeout(5)->timeout(10);
            if (filled(config('knowledge.qdrant_api_key'))) $http = $http->withHeader('api-key', config('knowledge.qdrant_api_key'));
            $response = $http->withOptions(['allow_redirects' => false])->get(rtrim($base, '/').'/collections');
            return $response->successful() ? ['available', 'Qdrant responded to an authenticated collections request.', $response->status()] : ['unavailable', 'Qdrant returned HTTP '.$response->status().'. Check the endpoint and key.', $response->status()];
        }
        if ($provider === 'ussd') return ['configuration_missing', 'USSD Manager is a separately operated service. Register its approved HTTPS endpoint and check it through its own operator tools.', null];
        return ['configuration_missing', 'Custom endpoints are recorded in the registry but are not contacted by this health check.', null];
    }

    private function providerStatus(Entra $entra, int $org): array
    {
        $pbx = PbxConnection::where('organization_id', $org)->where('driver', 'vitalpbx')->count();
        return [
            ['key' => 'vitalpbx', 'name' => 'VitalPBX', 'configured' => (bool) (config('vitalpbx.api_key') && config('vitalpbx.base_url')) || $pbx > 0, 'source' => $pbx ? 'Contact Centre profiles / VITALPBX_API_*' : '.env · VITALPBX_API_*', 'note' => $pbx.' organization PBX profile(s). Health check is a read-only tenants request.'],
            ['key' => 'txtconnect', 'name' => 'TXTConnect', 'configured' => (bool) (config('services.txtconnect.key') && config('services.txtconnect.sender')), 'source' => '.env · TXTCONNECT_*', 'note' => 'SMS sends and provider responses are handled by Module 09. Health probe sends no SMS.'],
            ['key' => 'microsoft365', 'name' => 'Microsoft 365 / Entra', 'configured' => $entra->ready(), 'source' => '.env · ENTRA_* and KNOWLEDGE_GRAPH_*', 'note' => 'SSO and approved Graph content use their existing Module 02 and 18 adapters.'],
            ['key' => 'qdrant', 'name' => 'Qdrant', 'configured' => (bool) (config('knowledge.semantic_enabled') && config('knowledge.qdrant_url')), 'source' => '.env · KNOWLEDGE_QDRANT_*', 'note' => 'Semantic indexing is managed by Module 18. Health check reads collection metadata.'],
            ['key' => 'ussd', 'name' => 'USSD Manager', 'configured' => false, 'source' => 'Separate USSD Manager', 'note' => 'No request payloads or secrets are sent by this ERP.'],
            ['key' => 'custom', 'name' => 'Custom API / Webhook', 'configured' => false, 'source' => 'Organization registry', 'note' => 'Registry entries are descriptive; arbitrary URLs are never probed.'],
        ];
    }

    private function authorizeUser(Request $request, string $permission): void
    {
        abort_unless(app(Modules::class)->enabled('integrations'), 404);
        $user = $request->user();
        $permitted = $user && ($permission === 'integrations.view'
            ? $user->canAny(['integrations.view', 'integrations.manage', 'integrations.tokens.manage', 'integrations.health.check'])
            : $user->can($permission));
        abort_unless($user && ($user->isSuperAdmin() || $permitted), 403);
        abort_unless($user->active_organization_id && $user->canAccessOrganization((int) $user->active_organization_id), 403);
    }
}
