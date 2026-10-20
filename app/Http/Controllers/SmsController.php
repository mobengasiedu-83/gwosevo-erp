<?php

namespace App\Http\Controllers;

use App\Domain\Administration\Modules;
use App\Jobs\SendSmsMessage;
use App\Models\SmsCampaign;
use App\Models\SmsConsent;
use App\Models\SmsMessage;
use App\Models\SmsTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SmsController extends Controller
{
    private function org(Request $request): int
    {
        $id = (int) $request->user()->active_organization_id;
        abort_unless($id && $request->user()->canAccessOrganization($id), 403);
        return $id;
    }

    private function enabled(): void { abort_unless(app(Modules::class)->enabled('sms'), 404); }

    public function index(Request $request)
    {
        $this->enabled(); abort_unless($request->user()->can('sms.view'), 403); $org = $this->org($request);
        $messages = SmsMessage::where('organization_id', $org)->latest()->paginate(20)->withQueryString();
        $templates = SmsTemplate::where('organization_id', $org)->where('is_active', true)->orderBy('name')->get();
        $consents = SmsConsent::where('organization_id', $org)->latest('updated_at')->paginate(10, ['*'], 'consents_page');
        $campaigns = SmsCampaign::where('organization_id', $org)->withCount('messages')->latest()->limit(10)->get();
        $stats = SmsMessage::where('organization_id', $org)->selectRaw("count(*) as total, sum(status = 'submitted') as submitted, sum(status = 'failed') as failed, sum(status = 'delivered') as delivered")->first();
        $configured = filled(config('services.txtconnect.key')) && filled(config('services.txtconnect.sender')) && str_starts_with((string) config('services.txtconnect.base_url'), 'https://');
        return view('sms.index', compact('messages', 'templates', 'consents', 'campaigns', 'stats', 'configured'));
    }

    public function template(Request $request)
    {
        $this->enabled(); abort_unless($request->user()->can('sms.manage'), 403); $org = $this->org($request);
        $data = $request->validate(['name' => ['required','string','max:120'], 'category' => ['required', Rule::in(['transactional','campaign'])], 'body' => ['required','string','max:1600']]);
        SmsTemplate::updateOrCreate(['organization_id' => $org, 'name' => $data['name']], [...$data, 'is_active' => true, 'created_by' => $request->user()->id]);
        return back()->with('success', 'SMS template saved.');
    }

    public function consent(Request $request)
    {
        $this->enabled(); abort_unless($request->user()->can('sms.consent.manage'), 403); $org = $this->org($request);
        $data = $request->validate(['phone' => ['required','string','max:40'], 'name' => ['nullable','string','max:150'], 'source' => ['required','string','max:120'], 'status' => ['required', Rule::in(['opted_in','opted_out'])]]);
        $data['phone'] = $this->normalizePhone($data['phone']);
        $now = now();
        SmsConsent::updateOrCreate(['organization_id' => $org, 'phone' => $data['phone']], [
            ...$data, 'consented_at' => $data['status'] === 'opted_in' ? $now : null,
            'opted_out_at' => $data['status'] === 'opted_out' ? $now : null, 'updated_by' => $request->user()->id,
        ]);
        return back()->with('success', 'SMS consent preference saved.');
    }

    public function send(Request $request)
    {
        $this->enabled(); abort_unless($request->user()->can('sms.send'), 403); $org = $this->org($request);
        abort_unless($this->configured(), 422, 'Configure TXTCONNECT_API_KEY and TXTCONNECT_SENDER in .env before sending.');
        $data = $request->validate(['phone' => ['required','string','max:40'], 'body' => ['required_without:template_id','nullable','string','max:1600'], 'template_id' => ['nullable','integer'], 'contact_id' => ['nullable','integer'], 'ticket_id' => ['nullable','integer']]);
        $phone = $this->normalizePhone($data['phone']);
        abort_unless(SmsConsent::where('organization_id', $org)->where('phone', $phone)->where('status', 'opted_in')->exists(), 422, 'This number is not opted in for SMS. Record valid consent first.');
        $this->validateRecords($org, $data);
        $body = $this->resolveBody($org, $data, 'transactional');
        $message = SmsMessage::create(['organization_id' => $org, 'contact_id' => $data['contact_id'] ?? null, 'ticket_id' => $data['ticket_id'] ?? null, 'to' => $phone, 'sender' => config('services.txtconnect.sender'), 'body' => $body, 'status' => 'queued', 'idempotency_key' => (string) Str::uuid(), 'created_by' => $request->user()->id]);
        SendSmsMessage::dispatch($message->id)->onQueue('sms');
        return back()->with('success', 'SMS queued for '.$phone.'.');
    }

    public function campaign(Request $request)
    {
        $this->enabled(); abort_unless($request->user()->can('sms.campaigns.manage'), 403); $org = $this->org($request);
        abort_unless($this->configured(), 422, 'Configure TXTConnect in .env before scheduling campaigns.');
        $data = $request->validate(['name' => ['required','string','max:160'], 'body' => ['required_without:template_id','nullable','string','max:1600'], 'template_id' => ['nullable','integer'], 'phones' => ['required','string','max:100000'], 'scheduled_at' => ['nullable','date','after_or_equal:now']]);
        $body = $this->resolveBody($org, $data, 'campaign');
        $phones = collect(preg_split('/[\r\n,;]+/', $data['phones']))->map(fn ($phone) => trim($phone))->filter()->map(fn ($phone) => $this->normalizePhone($phone))->unique()->values();
        abort_if($phones->isEmpty() || $phones->count() > 5000, 422, 'Enter between 1 and 5,000 recipient numbers.');
        $consented = SmsConsent::where('organization_id', $org)->where('status', 'opted_in')->whereIn('phone', $phones)->pluck('phone')->all();
        abort_if(count($consented) !== $phones->count(), 422, 'Every campaign recipient must have an active opted-in consent record.');
        $scheduled = ! empty($data['scheduled_at']) ? \Illuminate\Support\Carbon::parse($data['scheduled_at']) : null;
        $campaign = DB::transaction(function () use ($request, $org, $data, $body, $phones, $scheduled) {
            $campaign = SmsCampaign::create(['organization_id' => $org, 'name' => $data['name'], 'body' => $body, 'status' => $scheduled ? 'scheduled' : 'queued', 'scheduled_at' => $scheduled, 'started_at' => $scheduled ? null : now(), 'created_by' => $request->user()->id]);
            foreach ($phones as $phone) {
                $campaign->messages()->create(['organization_id' => $org, 'to' => $phone, 'sender' => config('services.txtconnect.sender'), 'body' => $body, 'status' => 'queued', 'idempotency_key' => (string) Str::uuid(), 'scheduled_at' => $scheduled, 'created_by' => $request->user()->id]);
            }
            return $campaign;
        });
        if (! $scheduled) $campaign->messages()->pluck('id')->each(fn ($id) => SendSmsMessage::dispatch($id)->onQueue('sms'));
        return back()->with('success', 'Campaign created for '.$phones->count().' opted-in recipients.'.($scheduled ? ' It will be queued at the scheduled time.' : ' Messages are queued.'));
    }

    public function retry(Request $request, SmsMessage $message)
    {
        $this->enabled(); abort_unless($request->user()->can('sms.manage'), 403); abort_unless($message->organization_id === $this->org($request), 404);
        abort_unless($message->status === 'failed', 422, 'Only a failed message can be manually retried.');
        abort_unless($message->attempts < 3, 422, 'This message has reached its three-attempt limit.');
        abort_unless($this->configured(), 422, 'Configure TXTConnect in .env before retrying.');
        $consented = SmsConsent::where('organization_id', $message->organization_id)->where('phone', $message->to)->where('status', 'opted_in')->exists();
        abort_unless($consented, 422, 'Recipient no longer has active SMS consent.');
        $message->update(['status' => 'queued', 'failure_reason' => null, 'provider_response' => null]);
        SendSmsMessage::dispatch($message->id)->onQueue('sms');
        return back()->with('success', 'Message queued for one controlled retry.');
    }

    private function resolveBody(int $org, array $data, string $category): string
    {
        if (empty($data['template_id'])) return trim((string) ($data['body'] ?? ''));
        $template = SmsTemplate::where('organization_id', $org)->where('category', $category)->where('is_active', true)->findOrFail($data['template_id']);
        return $template->body;
    }

    private function validateRecords(int $org, array $data): void
    {
        if (! empty($data['contact_id'])) abort_unless(\App\Models\Contact::where('organization_id', $org)->whereKey($data['contact_id'])->exists(), 422, 'Contact is outside the active organization.');
        if (! empty($data['ticket_id'])) abort_unless(\App\Models\Ticket::where('organization_id', $org)->whereKey($data['ticket_id'])->exists(), 422, 'Ticket is outside the active organization.');
    }

    private function configured(): bool
    {
        return filled(config('services.txtconnect.key')) && filled(config('services.txtconnect.sender')) && str_starts_with((string) config('services.txtconnect.base_url'), 'https://');
    }

    private function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/[\s().-]+/', '', trim($phone));
        abort_if(! preg_match('/^\+?[0-9]{7,15}$/', $phone), 422, 'Enter a valid international phone number, preferably in E.164 format.');
        return $phone;
    }
}
