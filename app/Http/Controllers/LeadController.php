<?php

namespace App\Http\Controllers;

use App\Domain\Administration\CustomFields;
use App\Domain\Administration\Settings;
use App\Domain\CRM\LeadService;
use App\Domain\Identity\LeadAccess;
use App\Models\CustomField;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LeadController extends Controller
{
    public function index(Request $r)
    {
        $this->authorize('viewAny', Lead::class);
        $leads = app(LeadAccess::class)->query($r->user())->with('owner')->when($r->filled('search'), fn ($q) => $q->where(fn ($x) => $x->where('first_name', 'like', '%'.$r->search.'%')->orWhere('last_name', 'like', '%'.$r->search.'%')->orWhere('company_name', 'like', '%'.$r->search.'%')))->latest()->paginate(app(Settings::class)->get('pagination'))->withQueryString();

        return view('leads.index', compact('leads'));
    }

    public function create()
    {
        $this->authorize('create', Lead::class);

        return view('leads.form', ['lead' => new Lead, 'customFields' => $this->customFields()]);
    }

    public function store(Request $r, LeadService $s)
    {
        $this->authorize('create', Lead::class);
        $lead = $s->create([...$this->data($r), 'organization_id' => $r->user()->active_organization_id], $r->user());

        return redirect()->route('leads.show', $lead)->with('success', 'Lead captured successfully.');
    }

    public function show(Lead $lead)
    {
        $this->authorize('view', $lead);

        return view('leads.show', ['lead' => $lead->load(['owner', 'activities.creator', 'statusHistory.actor', 'account', 'contact', 'opportunity']), 'owners' => $this->owners(), 'customFields' => $this->customFields()]);
    }

    public function edit(Lead $lead)
    {
        $this->authorize('update', $lead);

        return view('leads.form', ['lead' => $lead, 'customFields' => $this->customFields()]);
    }

    public function update(Request $r, Lead $lead)
    {
        $this->authorize('update', $lead);
        $data = $this->data($r);
        $data['custom_values'] = array_replace($lead->custom_values ?? [], $data['custom_values']);
        $lead->update([...$data, 'updated_by' => $r->user()->id]);

        return redirect()->route('leads.show', $lead)->with('success', 'Lead updated.');
    }

    public function assign(Request $r, Lead $lead, LeadService $s)
    {
        $this->authorize('assign', $lead);
        $d = $r->validate(['owner_id' => ['required', 'integer', Rule::exists('users', 'id')]]);
        $s->assign($lead, User::findOrFail($d['owner_id']), $r->user());

        return back()->with('success', 'Lead owner updated.');
    }

    public function qualify(Request $r, Lead $lead, LeadService $s)
    {
        $this->authorize('qualify', $lead);
        $d = $r->validate(['budget' => ['nullable', 'numeric', 'min:0'], 'authority' => ['nullable', 'string', 'max:255'], 'need' => ['nullable', 'string'], 'timeline' => ['nullable', 'string', 'max:255'], 'score' => ['required', 'integer', 'min:0', 'max:100']]);
        $s->qualify($lead, $d, $r->user());

        return back()->with('success', 'Lead qualified.');
    }

    public function convert(Lead $lead, LeadService $s)
    {
        $this->authorize('convert', $lead);
        $s->convert($lead, request()->user());

        return back()->with('success', 'Lead converted.');
    }

    public function activity(Request $r, Lead $lead)
    {
        $this->authorize('update', $lead);
        $d = $r->validate(['type' => ['required', Rule::in(['call', 'meeting', 'note', 'task'])], 'subject' => ['required', 'string', 'max:255'], 'body' => ['nullable', 'string'], 'due_at' => ['nullable', 'date']]);
        $lead->activities()->create([...$d, 'created_by' => $r->user()->id]);

        return back()->with('success', 'Activity added.');
    }

    private function customFields()
    {
        return CustomField::where('record_type', 'lead')->where('is_active', true)->get();
    }

    private function owners()
    {
        return User::where('status', 'active')->where(fn ($q) => $q->whereHas('organizations', fn ($q) => $q->where('organizations.id', auth()->user()->active_organization_id))->orWhereHas('roles', fn ($q) => $q->where('name', 'Administrator')))->orderBy('name')->get();
    }

    private function data(Request $r): array
    {
        $data = $r->validate(['first_name' => ['required', 'string', 'max:100'], 'last_name' => ['required', 'string', 'max:100'], 'email' => ['nullable', 'email'], 'phone' => ['nullable', 'string', 'max:50'], 'company_name' => ['nullable', 'string', 'max:255'], 'industry' => ['nullable', 'string', 'max:255'], 'interests' => ['nullable', 'string'], 'source' => ['required', Rule::in(['manual', 'website', 'api', 'referral', 'inbound_call', 'event'])], 'next_follow_up_at' => ['nullable', 'date'], 'custom_values' => ['nullable', 'array']]);
        $data['custom_values'] = app(CustomFields::class)->validate($data['custom_values'] ?? []);

        return $data;
    }
}
