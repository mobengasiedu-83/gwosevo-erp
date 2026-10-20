<?php

namespace App\Http\Controllers;

use App\Domain\Administration\Modules;
use App\Domain\CRM\LeadService;
use App\Domain\Identity\LeadAccess;
use App\Http\Controllers\Controller;
use App\Models\MarketingAffiliate;
use App\Models\MarketingCampaign;
use App\Models\MarketingConsent;
use App\Models\MarketingLandingPage;
use App\Models\MarketingTask;
use App\Models\Organization;
use App\Models\SmsConsent;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class MarketingController extends Controller
{
    private function enabled(): void { abort_unless(app(Modules::class)->enabled('marketing'), 404); }

    private function org(Request $request): int
    {
        $id = (int) $request->user()->active_organization_id;
        abort_unless($id && $request->user()->canAccessOrganization($id), 403);
        return $id;
    }

    public function index(Request $request, LeadAccess $access)
    {
        $this->enabled(); abort_unless($request->user()->can('marketing.view'), 403); $org = $this->org($request);
        $campaigns = MarketingCampaign::where('organization_id', $org)->with(['owner', 'landingPages'])->withCount(['tasks'])->latest()->paginate(15)->withQueryString();
        $campaignOptions = MarketingCampaign::where('organization_id', $org)->orderBy('name')->get();
        $all = MarketingCampaign::where('organization_id', $org);
        $leads = app(Modules::class)->enabled('crm') && $request->user()->can('leads.view') ? $access->query($request->user(), $org) : null;
        $leadCount = $leads ? (clone $leads)->whereNotNull('marketing_campaign_id')->count() : null;
        $convertedCount = $leads ? (clone $leads)->whereNotNull('marketing_campaign_id')->whereNotNull('converted_at')->count() : null;
        $stats = ['campaigns' => (clone $all)->count(), 'active' => (clone $all)->where('status', 'active')->count(), 'leads' => $leadCount, 'converted' => $convertedCount];
        $affiliates = MarketingAffiliate::where('organization_id', $org)->orderBy('name')->limit(50)->get();
        $owners = User::where('status', 'active')->whereHas('organizations', fn ($q) => $q->whereKey($org))->orderBy('name')->get();
        $sources = config('crm.sources', []);
        $industries = $leads ? (clone $leads)->whereNotNull('industry')->where('industry', '!=', '')->distinct()->orderBy('industry')->limit(100)->pluck('industry') : collect();
        $consents = $request->user()->can('marketing.consent.manage')
            ? MarketingConsent::where('organization_id', $org)->latest('updated_at')->limit(20)->get()
            : collect();
        return view('marketing.index', compact('campaigns', 'campaignOptions', 'stats', 'affiliates', 'owners', 'sources', 'industries', 'consents'));
    }

    public function storeCampaign(Request $request, LeadAccess $access)
    {
        $this->enabled(); abort_unless($request->user()->can('marketing.manage'), 403); $org = $this->org($request);
        $data = $request->validate([
            'name' => ['required','string','max:160'], 'objective' => ['nullable','string','max:5000'],
            'channel' => ['required', Rule::in(['email','sms','digital','event','referral'])],
            'starts_on' => ['nullable','date'], 'ends_on' => ['nullable','date','after_or_equal:starts_on'],
            'budget' => ['nullable','numeric','min:0','max:9999999999999.99'], 'owner_id' => ['nullable','integer'],
            'audience_source' => ['nullable', Rule::in(array_keys(config('crm.sources', [])))],
            'audience_status' => ['nullable', Rule::in(['new','assigned','qualified','converted','disqualified'])],
            'audience_industry' => ['nullable','string','max:255'],
        ]);
        if (! empty($data['owner_id'])) abort_unless(User::where('status','active')->whereHas('organizations',fn ($q) => $q->whereKey($org))->whereKey($data['owner_id'])->exists(), 422, 'Campaign owner must be an active member of this organization.');
        $rules = array_filter(['source' => $data['audience_source'] ?? null, 'status' => $data['audience_status'] ?? null, 'industry' => $data['audience_industry'] ?? null]);
        $audienceCount = null;
        if ($rules) {
            abort_unless(app(Modules::class)->enabled('crm') && $request->user()->can('leads.view'), 403, 'CRM audience filters require CRM access.');
            $audienceCount = $this->audienceQuery($access, $request->user(), $org, $rules)->count();
        }
        MarketingCampaign::create(['organization_id' => $org, 'name' => $data['name'], 'objective' => $data['objective'] ?? null, 'channel' => $data['channel'], 'status' => 'planned', 'starts_on' => $data['starts_on'] ?? null, 'ends_on' => $data['ends_on'] ?? null, 'budget' => $data['budget'] ?? null, 'actual_spend' => 0, 'audience_rules' => $rules, 'owner_id' => $data['owner_id'] ?? null, 'created_by' => $request->user()->id]);
        $message = 'Campaign saved as planned. No messages are sent by the marketing module.';
        if ($audienceCount !== null) $message .= ' The selected CRM audience currently contains '.$audienceCount.' leads.';
        return back()->with('success', $message);
    }

    public function show(Request $request, MarketingCampaign $campaign, LeadAccess $access)
    {
        $this->enabled(); abort_unless($request->user()->can('marketing.view'), 403); abort_unless($campaign->organization_id === $this->org($request), 404);
        $campaign->load(['owner', 'landingPages', 'tasks' => fn ($q) => $q->with('assignee')->orderByRaw('completed_at IS NOT NULL')->orderBy('due_at')]);
        $leadQuery = app(Modules::class)->enabled('crm') && $request->user()->can('leads.view') ? $access->query($request->user(), $campaign->organization_id)->where('marketing_campaign_id', $campaign->id) : null;
        $leads = $leadQuery ? (clone $leadQuery)->with(['owner', 'marketingAffiliate'])->latest()->paginate(20) : null;
        $metrics = $leadQuery ? ['leads' => (clone $leadQuery)->count(), 'converted' => (clone $leadQuery)->whereNotNull('converted_at')->count(), 'conversion_rate' => (clone $leadQuery)->count() ? round((clone $leadQuery)->whereNotNull('converted_at')->count() / (clone $leadQuery)->count() * 100, 1) : 0] : null;
        $audienceCount = $campaign->audience_rules && $request->user()->can('leads.view') && app(Modules::class)->enabled('crm') ? $this->audienceQuery($access, $request->user(), $campaign->organization_id, $campaign->audience_rules)->count() : null;
        $owners = User::where('status', 'active')->whereHas('organizations', fn ($q) => $q->whereKey($campaign->organization_id))->orderBy('name')->get();
        return view('marketing.show', compact('campaign', 'leads', 'metrics', 'audienceCount', 'owners'));
    }

    public function updateCampaign(Request $request, MarketingCampaign $campaign)
    {
        $this->enabled(); abort_unless($request->user()->can('marketing.manage'), 403); abort_unless($campaign->organization_id === $this->org($request), 404);
        $data = $request->validate(['status' => ['required', Rule::in(['planned','active','paused','completed'])], 'actual_spend' => ['required','numeric','min:0','max:9999999999999.99'], 'owner_id' => ['nullable','integer']]);
        if (! empty($data['owner_id'])) abort_unless(User::where('status','active')->whereHas('organizations',fn ($q) => $q->whereKey($campaign->organization_id))->whereKey($data['owner_id'])->exists(), 422, 'Campaign owner must be an active member of this organization.');
        $campaign->update($data);
        return back()->with('success', 'Campaign status and actual spend updated.');
    }

    public function task(Request $request, MarketingCampaign $campaign)
    {
        $this->enabled(); abort_unless($request->user()->can('marketing.tasks.manage'), 403); abort_unless($campaign->organization_id === $this->org($request), 404);
        $data = $request->validate(['subject' => ['required','string','max:180'], 'description' => ['nullable','string','max:5000'], 'due_at' => ['nullable','date'], 'assigned_to_id' => ['nullable','integer']]);
        if (! empty($data['assigned_to_id'])) abort_unless(User::where('status','active')->whereHas('organizations',fn ($q) => $q->whereKey($campaign->organization_id))->whereKey($data['assigned_to_id'])->exists(), 422, 'Assignee must be an active member of this organization.');
        $campaign->tasks()->create([...$data, 'organization_id' => $campaign->organization_id, 'created_by' => $request->user()->id]);
        return back()->with('success', 'Campaign task created.');
    }

    public function completeTask(Request $request, MarketingTask $task)
    {
        $this->enabled(); abort_unless($request->user()->can('marketing.tasks.manage'), 403); abort_unless($task->organization_id === $this->org($request), 404);
        $task->update(['completed_at' => $task->completed_at ? null : now()]);
        return back()->with('success', $task->completed_at ? 'Task marked complete.' : 'Task reopened.');
    }

    public function landingPage(Request $request)
    {
        $this->enabled(); abort_unless($request->user()->can('marketing.landing_pages.manage'), 403); $org = $this->org($request);
        $data = $request->validate(['campaign_id' => ['required','integer'], 'title' => ['required','string','max:180'], 'description' => ['nullable','string','max:5000'], 'slug' => ['nullable','string','max:100','regex:/^[A-Za-z0-9][A-Za-z0-9-]*$/']]);
        $campaign = MarketingCampaign::where('organization_id', $org)->findOrFail($data['campaign_id']);
        $slug = Str::slug($data['slug'] ?: $data['title']);
        abort_if($slug === '' || MarketingLandingPage::where('slug', $slug)->exists(), 422, 'Choose a unique URL slug.');
        $page = MarketingLandingPage::create(['organization_id' => $org, 'campaign_id' => $campaign->id, 'slug' => $slug, 'title' => $data['title'], 'description' => $data['description'] ?? null, 'is_published' => $request->boolean('is_published'), 'created_by' => $request->user()->id]);
        return back()->with('success', 'Landing page saved: '.route('marketing.landing.show', $page->slug));
    }

    public function affiliate(Request $request)
    {
        $this->enabled(); abort_unless($request->user()->can('marketing.affiliates.manage'), 403); $org = $this->org($request);
        $data = $request->validate(['name' => ['required','string','max:160'], 'code' => ['required','alpha_dash','max:50'], 'email' => ['nullable','email','max:255'], 'approved_commission_percent' => ['nullable','numeric','min:0','max:100'], 'notes' => ['nullable','string','max:3000']]);
        $exists = MarketingAffiliate::where('organization_id', $org)->where('code', $data['code'])->exists();
        abort_if($exists, 422, 'Affiliate code already exists in this organization.');
        MarketingAffiliate::create([...$data, 'organization_id' => $org, 'created_by' => $request->user()->id, 'is_active' => true]);
        return back()->with('success', 'Affiliate saved. Referral attribution is tracked; this module does not calculate or pay commissions.');
    }

    public function consent(Request $request)
    {
        $this->enabled(); abort_unless($request->user()->can('marketing.consent.manage'), 403); $org = $this->org($request);
        $data = $request->validate(['channel' => ['required', Rule::in(['email','sms'])], 'destination' => ['required','string','max:255'], 'status' => ['required', Rule::in(['opted_in','withdrawn'])], 'source' => ['required','string','max:120'], 'lead_id' => ['nullable','integer']]);
        if (! empty($data['lead_id'])) {
            $lead = \App\Models\Lead::where('organization_id', $org)->findOrFail($data['lead_id']);
            abort_unless(app(LeadAccess::class)->allows($request->user(), $lead), 403, 'You cannot change preferences for this lead.');
        }
        $destination = $data['channel'] === 'email' ? mb_strtolower(trim($data['destination'])) : $this->normalizePhone($data['destination']);
        if ($data['channel'] === 'email') validator(['destination' => $destination], ['destination' => ['required','email','max:255']])->validate();
        $this->saveConsent($org, $data['channel'], $destination, $data['status'], $data['source'], $data['lead_id'] ?? null, $request->user()->id);
        if ($data['channel'] === 'sms') {
            SmsConsent::updateOrCreate(['organization_id' => $org, 'phone' => $destination], ['name' => null, 'source' => 'Marketing consent: '.$data['source'], 'status' => $data['status'] === 'opted_in' ? 'opted_in' : 'opted_out', 'consented_at' => $data['status'] === 'opted_in' ? now() : null, 'opted_out_at' => $data['status'] === 'withdrawn' ? now() : null, 'updated_by' => $request->user()->id]);
        }
        return back()->with('success', 'Marketing contact preference saved.');
    }

    public function publicLanding(string $slug)
    {
        $this->enabled();
        $page = MarketingLandingPage::where('slug', $slug)->where('is_published', true)->with(['campaign', 'campaign.organization'])->firstOrFail();
        abort_unless($page->campaign->status === 'active' && $page->campaign->organization->is_active, 404);
        return response()->view('marketing.landing', compact('page'))->header('Referrer-Policy', 'strict-origin-when-cross-origin')->header('X-Robots-Tag', 'noindex');
    }

    public function publicLead(Request $request, string $slug, LeadService $service)
    {
        $this->enabled();
        $page = MarketingLandingPage::where('slug', $slug)->where('is_published', true)->with(['campaign', 'campaign.organization'])->firstOrFail();
        $campaign = $page->campaign;
        abort_unless($campaign->status === 'active' && $campaign->organization->is_active, 404);
        $data = $request->validate(['first_name' => ['required','string','max:100'], 'last_name' => ['required','string','max:100'], 'email' => ['nullable','required_without:phone','email','max:255'], 'phone' => ['nullable','required_without:email','string','max:50'], 'company_name' => ['nullable','string','max:255'], 'industry' => ['nullable','string','max:255'], 'interests' => ['nullable','string','max:3000'], 'email_consent' => ['nullable','accepted'], 'sms_consent' => ['nullable','accepted'], 'affiliate_code' => ['nullable','string','max:50'], 'website' => ['nullable','string','max:255']]);
        if ($request->filled('website')) return redirect()->route('marketing.landing.show', $slug)->with('status', 'Thanks. Your enquiry has been received.');
        $org = $campaign->organization_id;
        $email = isset($data['email']) ? mb_strtolower(trim($data['email'])) : null;
        $phone = isset($data['phone']) ? $this->normalizePhone($data['phone']) : null;
        $affiliate = ! empty($data['affiliate_code']) ? MarketingAffiliate::where('organization_id', $org)->where('code', $data['affiliate_code'])->where('is_active', true)->first() : null;
        abort_if(! empty($data['affiliate_code']) && ! $affiliate, 422, 'Referral code is not active.');
        $source = in_array($campaign->channel, ['event','referral'], true) ? $campaign->channel : 'campaign';
        $lead = DB::transaction(function () use ($service, $data, $campaign, $page, $org, $email, $phone, $affiliate, $source) {
            $lead = $service->create(['first_name' => $data['first_name'], 'last_name' => $data['last_name'], 'email' => $email, 'phone' => $phone, 'company_name' => $data['company_name'] ?? null, 'industry' => $data['industry'] ?? null, 'interests' => $data['interests'] ?? null, 'source' => $source, 'source_detail' => 'Landing page: '.$page->slug, 'marketing_campaign_id' => $campaign->id, 'marketing_affiliate_id' => $affiliate?->id, 'organization_id' => $org], null);
            if ($email && ! empty($data['email_consent'])) $this->saveConsent($org, 'email', $email, 'opted_in', 'Landing page: '.$page->slug, $lead->id, null);
            if ($phone && ! empty($data['sms_consent'])) {
                $this->saveConsent($org, 'sms', $phone, 'opted_in', 'Landing page: '.$page->slug, $lead->id, null);
                SmsConsent::updateOrCreate(['organization_id' => $org, 'phone' => $phone], ['name' => $lead->full_name, 'source' => 'Marketing landing page: '.$page->slug, 'status' => 'opted_in', 'consented_at' => now(), 'opted_out_at' => null, 'updated_by' => null]);
            }
            return $lead;
        });
        return redirect()->route('marketing.landing.show', $slug)->with('status', 'Thanks. Your enquiry has been received. Reference: '.$lead->reference);
    }

    private function audienceQuery(LeadAccess $access, User $user, int $org, array $rules)
    {
        return $access->query($user, $org)->whereNull('archived_at')
            ->when($rules['source'] ?? null, fn ($q, $value) => $q->where('source', $value))
            ->when($rules['status'] ?? null, fn ($q, $value) => $q->where('status', $value))
            ->when($rules['industry'] ?? null, fn ($q, $value) => $q->where('industry', $value));
    }

    private function saveConsent(int $org, string $channel, string $destination, string $status, ?string $source, ?int $leadId, ?int $actorId): MarketingConsent
    {
        return MarketingConsent::updateOrCreate(['organization_id' => $org, 'channel' => $channel, 'destination' => $destination], ['lead_id' => $leadId, 'status' => $status, 'source' => $source, 'consented_at' => $status === 'opted_in' ? now() : null, 'withdrawn_at' => $status === 'withdrawn' ? now() : null, 'updated_by' => $actorId]);
    }

    private function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/[\s().-]+/', '', trim($phone));
        abort_if(! preg_match('/^\+?[0-9]{7,15}$/', $phone), 422, 'Enter a valid international phone number.');
        return $phone;
    }
}
