<?php

namespace App\Http\Controllers;

use App\Domain\Identity\LeadAccess;
use App\Domain\Administration\NumberSequence;
use App\Models\Account;
use App\Models\Contact;
use App\Models\Lead;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CrmWorkspaceController extends Controller
{
    public function dashboard(Request $request)
    {
        $this->authorize('viewAny', Lead::class);
        $leads = app(LeadAccess::class)->query($request->user())->whereNull('archived_at');
        $base = clone $leads;
        $counts = [
            'leads' => (clone $base)->count(),
            'new' => (clone $base)->whereIn('status', ['new', 'assigned'])->count(),
            'qualified' => (clone $base)->where('status', 'qualified')->count(),
            'converted' => (clone $base)->where('status', 'converted')->count(),
            'followups' => (clone $base)->whereNotNull('next_follow_up_at')->where('next_follow_up_at', '<=', now())->whereNotIn('status', ['converted', 'disqualified'])->count(),
        ];
        $sourceRows = (clone $base)->select('source', DB::raw('count(*) as total'))->groupBy('source')->orderByDesc('total')->limit(8)->get();
        $followups = (clone $base)->with('owner')->whereNotNull('next_follow_up_at')->whereNotIn('status', ['converted', 'disqualified'])->orderBy('next_follow_up_at')->limit(10)->get();
        $recent = (clone $base)->with('owner')->latest()->limit(8)->get();

        return view('crm.workspace', ['section' => 'CRM Dashboard', 'counts' => $counts, 'sourceRows' => $sourceRows, 'followups' => $followups, 'leads' => $recent]);
    }

    public function accounts(Request $request)
    {
        $this->authorize('viewAny', Lead::class);
        $accounts = $this->accountQuery($request)->with('owner')->withCount('contacts')->orderBy('name')->paginate(25)->withQueryString();
        $owners = \App\Models\User::where('status','active')->whereHas('organizations', fn ($q) => $q->where('organizations.id',$request->user()->active_organization_id))->orderBy('name')->get()->filter(fn ($user) => $user->can('leads.view'))->values();
        $editingAccount = $request->filled('edit') ? $this->accountQuery($request)->findOrFail($request->integer('edit')) : new Account;
        return view('crm.workspace', ['section' => 'Accounts', 'accounts' => $accounts, 'owners' => $owners, 'editingAccount' => $editingAccount]);
    }

    public function accountStore(Request $request)
    {
        $this->authorize('create', Lead::class);
        $org = $request->user()->active_organization_id;
        $data = $request->validate(['name' => ['required','string','max:255'], 'email' => ['nullable','email','max:255'], 'phone' => ['nullable','string','max:50'], 'industry' => ['nullable','string','max:255'], 'owner_id' => ['nullable','integer',Rule::exists('users','id')->where('status','active')]]);
        if (! empty($data['owner_id'])) {
            $owner = \App\Models\User::findOrFail($data['owner_id']);
            abort_unless($owner->canAccessOrganization($org) && $owner->can('leads.view'), 422, 'Account owner must be an active CRM user in this organization.');
        }
        Account::create(['organization_id' => $org, 'reference' => app(NumberSequence::class)->next('customer'), 'created_by' => $request->user()->id, ...$data]);
        return back()->with('success', 'Account created.');
    }

    public function accountUpdate(Request $request, int $account)
    {
        abort_unless($request->user()->isSuperAdmin() || $request->user()->can('leads.update'), 403);
        $record = $this->accountQuery($request)->findOrFail($account);
        $org = $request->user()->active_organization_id;
        $data = $request->validate(['name' => ['required','string','max:255'], 'email' => ['nullable','email','max:255'], 'phone' => ['nullable','string','max:50'], 'industry' => ['nullable','string','max:255'], 'owner_id' => ['nullable','integer',Rule::exists('users','id')->where('status','active')]]);
        if (! empty($data['owner_id'])) {
            $owner = \App\Models\User::findOrFail($data['owner_id']);
            abort_unless($owner->canAccessOrganization($org) && $owner->can('leads.view'), 422, 'Account owner must be an active CRM user in this organization.');
        }
        $record->update($data);
        return redirect()->route('crm.accounts')->with('success', 'Account updated.');
    }

    public function contacts(Request $request)
    {
        $this->authorize('viewAny', Lead::class);
        $contacts = $this->contactQuery($request)->with('account')->orderBy('last_name')->orderBy('first_name')->paginate(25)->withQueryString();
        $accounts = $this->accountQuery($request)->orderBy('name')->get();
        $editingContact = $request->filled('edit') ? $this->contactQuery($request)->findOrFail($request->integer('edit')) : new Contact;
        return view('crm.workspace', ['section' => 'Contacts', 'contacts' => $contacts, 'accounts' => $accounts, 'editingContact' => $editingContact]);
    }

    public function contactStore(Request $request)
    {
        $this->authorize('create', Lead::class);
        $org = $request->user()->active_organization_id;
        $data = $request->validate(['account_id' => ['nullable','integer', Rule::exists('accounts','id')->where('organization_id',$org)], 'first_name' => ['required','string','max:100'], 'last_name' => ['required','string','max:100'], 'email' => ['nullable','email','max:255'], 'phone' => ['nullable','string','max:50'], 'preferred_channel' => ['nullable',Rule::in(['email','sms','phone','none'])], 'email_opt_in' => ['sometimes','boolean'], 'sms_opt_in' => ['sometimes','boolean']]);
        if (! empty($data['account_id']) && ! $this->accountQuery($request)->whereKey($data['account_id'])->exists()) abort(403, 'You cannot attach this contact to that account.');
        Contact::create(['organization_id' => $org, 'created_by' => $request->user()->id, ...$data]);
        return back()->with('success', 'Contact created.');
    }

    public function contactUpdate(Request $request, int $contact)
    {
        abort_unless($request->user()->isSuperAdmin() || $request->user()->can('leads.update'), 403);
        $record = $this->contactQuery($request)->findOrFail($contact);
        $org = $request->user()->active_organization_id;
        $data = $request->validate(['account_id' => ['nullable','integer',Rule::exists('accounts','id')->where('organization_id',$org)], 'first_name' => ['required','string','max:100'], 'last_name' => ['required','string','max:100'], 'email' => ['nullable','email','max:255'], 'phone' => ['nullable','string','max:50'], 'preferred_channel' => ['nullable',Rule::in(['email','sms','phone','none'])], 'email_opt_in' => ['sometimes','boolean'], 'sms_opt_in' => ['sometimes','boolean']]);
        if (! empty($data['account_id']) && ! $this->accountQuery($request)->whereKey($data['account_id'])->exists()) abort(403, 'You cannot attach this contact to that account.');
        $record->update($data);
        return redirect()->route('crm.contacts')->with('success', 'Contact updated.');
    }

    public function sources(Request $request)
    {
        $this->authorize('viewAny', Lead::class);
        $org = $request->user()->active_organization_id;
        $sources = DB::table('crm_lead_sources')->where('organization_id', $org)->orderBy('name')->get();
        $usage = app(LeadAccess::class)->query($request->user())->select('source', DB::raw('count(*) as total'))->groupBy('source')->pluck('total','source');
        return view('crm.workspace', ['section' => 'Lead Sources', 'sources' => $sources, 'usage' => $usage]);
    }

    public function sourceStore(Request $request)
    {
        abort_unless($request->user()->isSuperAdmin() || $request->user()->can('leads.rules.manage'), 403);
        $org = $request->user()->active_organization_id;
        $data = $request->validate(['name' => ['required','string','max:120'], 'key' => ['required','alpha_dash','max:60', Rule::unique('crm_lead_sources','key')->where('organization_id',$org)]]);
        DB::table('crm_lead_sources')->insert(['organization_id' => $org, 'key' => strtolower($data['key']), 'name' => $data['name'], 'created_by' => $request->user()->id, 'created_at' => now(), 'updated_at' => now()]);
        return back()->with('success', 'Lead source added.');
    }

    public function sourceUpdate(Request $request, int $source)
    {
        abort_unless($request->user()->isSuperAdmin() || $request->user()->can('leads.rules.manage'), 403);
        $org = $request->user()->active_organization_id;
        $record = DB::table('crm_lead_sources')->where('organization_id',$org)->where('id',$source)->first();
        abort_if(! $record, 404);
        $data = $request->validate(['name' => ['required','string','max:120'], 'is_active' => ['required','boolean']]);
        DB::table('crm_lead_sources')->where('id',$record->id)->update([...$data, 'updated_at' => now()]);
        return back()->with('success', 'Lead source updated.');
    }

    public function segments(Request $request)
    {
        $this->authorize('viewAny', Lead::class);
        $segments = DB::table('crm_saved_segments')->where('organization_id', $request->user()->active_organization_id)->orderBy('name')->get();
        return view('crm.workspace', ['section' => 'Segmentation', 'segments' => $segments, 'sources' => $this->sourceOptions($request)]);
    }

    public function segmentStore(Request $request)
    {
        $this->authorize('create', Lead::class);
        $data = $request->validate(['name' => ['required','string','max:160'], 'status' => ['nullable', Rule::in(Lead::STATUSES)], 'source' => ['nullable', Rule::in(array_keys($this->sourceOptions($request)))], 'industry' => ['nullable','string','max:255']]);
        $filters = array_filter(collect($data)->except('name')->all(), fn ($value) => filled($value));
        DB::table('crm_saved_segments')->insert(['organization_id' => $request->user()->active_organization_id, 'name' => $data['name'], 'filters' => json_encode($filters), 'created_by' => $request->user()->id, 'created_at' => now(), 'updated_at' => now()]);
        return redirect()->route('crm.segments')->with('success', 'Segment saved.');
    }

    public function segmentShow(Request $request, int $segment)
    {
        $this->authorize('viewAny', Lead::class);
        $saved = DB::table('crm_saved_segments')->where('organization_id', $request->user()->active_organization_id)->where('id', $segment)->first();
        abort_if(! $saved, 404);
        return $this->leadList($request, 'Segmentation', json_decode($saved->filters, true) ?: [], ['segment' => $saved]);
    }

    public function segmentDestroy(Request $request, int $segment)
    {
        $org = $request->user()->active_organization_id;
        $record = DB::table('crm_saved_segments')->where('organization_id',$org)->where('id',$segment)->first();
        abort_if(! $record, 404);
        abort_unless($request->user()->isSuperAdmin() || (int) $record->created_by === (int) $request->user()->id || $request->user()->can('leads.rules.manage'), 403);
        DB::table('crm_saved_segments')->where('id',$record->id)->delete();
        return redirect()->route('crm.segments')->with('success', 'Saved segment deleted.');
    }

    public function qualification(Request $request)
    {
        $this->authorize('viewAny', Lead::class);
        $leads = app(LeadAccess::class)->query($request->user())->whereNull('archived_at')->whereIn('status',['new','assigned','qualified'])->with('owner')->orderByDesc('score')->latest()->paginate(25)->withQueryString();
        return view('crm.workspace', ['section' => 'Qualification', 'leads' => $leads]);
    }

    public function conversion(Request $request)
    {
        $this->authorize('viewAny', Lead::class);
        $leads = app(LeadAccess::class)->query($request->user())->whereNull('archived_at')->whereIn('status',['qualified','converted'])->with('owner')->latest()->paginate(25)->withQueryString();
        return view('crm.workspace', ['section' => 'Conversion', 'leads' => $leads]);
    }

    public function interactions(Request $request)
    {
        $this->authorize('viewAny', Lead::class);
        $ids = app(LeadAccess::class)->query($request->user())->select('id');
        $activities = DB::table('lead_activities')->join('leads','leads.id','=','lead_activities.lead_id')->whereIn('lead_activities.lead_id',$ids)->orderByDesc('lead_activities.created_at')->limit(100)->get(['lead_activities.*','leads.reference','leads.first_name','leads.last_name']);
        return view('crm.workspace', ['section' => 'Interactions', 'activities' => $activities]);
    }

    public function followups(Request $request)
    {
        $this->authorize('viewAny', Lead::class);
        $ids = app(LeadAccess::class)->query($request->user())->whereNull('archived_at')->whereNotIn('status',['converted','disqualified'])->whereNotNull('next_follow_up_at')->select('id');
        $leads = Lead::whereIn('id',$ids)->with('owner')->orderBy('next_follow_up_at')->paginate(25)->withQueryString();
        return view('crm.workspace', ['section' => 'Follow-ups', 'leads' => $leads]);
    }

    private function leadList(Request $request, string $section, array $filters = [], array $extra = [])
    {
        $this->authorize('viewAny', Lead::class);
        $query = app(LeadAccess::class)->query($request->user())->whereNull('archived_at')->with('owner');
        foreach (['status','source'] as $key) if (! empty($filters[$key])) $query->where($key, $filters[$key]);
        if (! empty($filters['industry'])) $query->where('industry',$filters['industry']);
        $leads = $query->latest()->paginate(25)->withQueryString();
        return view('crm.workspace', ['section' => $section, 'leads' => $leads, ...$extra]);
    }

    private function accountQuery(Request $request)
    {
        $query = Account::where('organization_id', $request->user()->active_organization_id);
        if (! $request->user()->isSuperAdmin()) {
            $membership = $request->user()->organizations()->whereKey($request->user()->active_organization_id)->first()?->pivot;
            if ($membership && $membership->scope !== 'organization') {
                $visibleLeadIds = app(LeadAccess::class)->query($request->user())->select('account_id')->whereNotNull('account_id');
                $query->where(fn ($q) => $q->where('created_by', $request->user()->id)->orWhere('owner_id', $request->user()->id)->orWhereIn('id', $visibleLeadIds));
            }
        }
        return $query;
    }

    private function contactQuery(Request $request)
    {
        $query = Contact::where('organization_id', $request->user()->active_organization_id);
        if (! $request->user()->isSuperAdmin()) {
            $membership = $request->user()->organizations()->whereKey($request->user()->active_organization_id)->first()?->pivot;
            if ($membership && $membership->scope !== 'organization') {
                $visibleLeadIds = app(LeadAccess::class)->query($request->user())->select('contact_id')->whereNotNull('contact_id');
                $query->where(fn ($q) => $q->where('created_by', $request->user()->id)->orWhereIn('id', $visibleLeadIds));
            }
        }
        return $query;
    }

    private function sourceOptions(Request $request): array
    {
        return config('crm.sources', []) + DB::table('crm_lead_sources')->where('organization_id',$request->user()->active_organization_id)->where('is_active',true)->pluck('name','key')->all();
    }
}
