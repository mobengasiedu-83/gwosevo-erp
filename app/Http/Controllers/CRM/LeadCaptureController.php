<?php

namespace App\Http\Controllers\CRM;

use App\Domain\Administration\Modules;
use App\Domain\CRM\LeadService;
use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class LeadCaptureController extends Controller
{
    public function website(Request $request, Organization $organization, LeadService $service, Modules $modules)
    {
        abort_unless($organization->is_active && $modules->enabled('crm'), 404);
        $secret = (string) config('crm.website_capture_secret');
        abort_if($secret === '', 503, 'Website lead capture is not configured.');
        $signature = (string) $request->header('X-CRM-Signature');
        abort_unless(strlen($signature) === 64 && hash_equals(hash_hmac('sha256', $request->getContent(), $secret), $signature), 403, 'Invalid capture signature.');
        $data = $this->validatePayload($request);
        $lead = $this->idempotentCreate($organization, $request, $data, null, $service);

        return response()->json(['accepted' => true, 'reference' => $lead->reference], 202);
    }

    public function api(Request $request, LeadService $service, Modules $modules)
    {
        $this->authorize('create', Lead::class);
        abort_unless($modules->enabled('crm'), 404);
        $data = $this->validatePayload($request);
        $organization = Organization::whereKey($request->user()->active_organization_id)->firstOrFail();
        $lead = $this->idempotentCreate($organization, $request, $data, $request->user(), $service);

        return response()->json(['id' => $lead->id, 'reference' => $lead->reference, 'status' => $lead->status], 201);
    }

    private function idempotentCreate(Organization $organization, Request $request, array $data, ?User $actor, LeadService $service): Lead
    {
        $key = (string) $request->header('Idempotency-Key');
        abort_unless(preg_match('/^[A-Za-z0-9._:-]{8,128}$/', $key), 422, 'A valid Idempotency-Key header is required.');
        $payloadHash = hash('sha256', json_encode(['source' => $actor ? 'api' : 'website', 'data' => $data], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return DB::transaction(function () use ($organization, $key, $payloadHash, $data, $actor, $service) {
            DB::table('lead_capture_keys')->insertOrIgnore(['organization_id' => $organization->id, 'key' => $key, 'payload_hash' => $payloadHash, 'created_at' => now(), 'updated_at' => now()]);
            $saved = DB::table('lead_capture_keys')->where('organization_id', $organization->id)->where('key', $key)->lockForUpdate()->first();
            abort_unless($saved && hash_equals($saved->payload_hash, $payloadHash), 409, 'Idempotency-Key was already used for a different request.');
            if ($saved->lead_id) return Lead::findOrFail($saved->lead_id);
            $lead = $service->create([...$data, 'source' => $actor ? 'api' : 'website', 'organization_id' => $organization->id], $actor);
            DB::table('lead_capture_keys')->where('id', $saved->id)->update(['lead_id' => $lead->id, 'updated_at' => now()]);

            return $lead;
        });
    }

    private function validatePayload(Request $request): array
    {
        return Validator::make($request->all(), [
            'first_name' => ['required', 'string', 'max:100'], 'last_name' => ['required', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:255'], 'phone' => ['nullable', 'string', 'max:50'],
            'company_name' => ['nullable', 'string', 'max:255'], 'industry' => ['nullable', 'string', 'max:255'],
            'interests' => ['nullable', 'string', 'max:5000'], 'source_detail' => ['nullable', 'string', 'max:255'],
        ])->validate();
    }
}
