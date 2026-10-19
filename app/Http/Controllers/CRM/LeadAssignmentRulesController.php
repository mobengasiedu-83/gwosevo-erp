<?php

namespace App\Http\Controllers\CRM;

use App\Http\Controllers\Controller;
use App\Models\AuditEvent;
use App\Models\Lead;
use App\Models\LeadAssignmentRule;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LeadAssignmentRulesController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('manageRules', \App\Models\Lead::class);
        $organizationId = $request->user()->active_organization_id;
        $rules = LeadAssignmentRule::with('owner')->where('organization_id', $organizationId)->orderBy('priority')->get();
        $owners = User::where('status', 'active')->whereHas('organizations', fn ($q) => $q->where('organizations.id', $organizationId))->orderBy('name')->get()->filter(fn (User $u) => $u->can('leads.view'));

        return view('leads.assignment-rules', ['rules' => $rules, 'owners' => $owners, 'sources' => config('crm.sources')]);
    }

    public function store(Request $request)
    {
        $this->authorize('manageRules', \App\Models\Lead::class);
        $organizationId = $request->user()->active_organization_id;
        $data = $request->validate([
            'source' => ['nullable', Rule::in(array_keys(config('crm.sources')))], 'industry' => ['nullable', 'string', 'max:255'],
            'owner_id' => ['required', 'integer', Rule::exists('user_organization_memberships', 'user_id')->where('organization_id', $organizationId)],
            'priority' => ['required', 'integer', 'min:1', 'max:10000'], 'is_active' => ['nullable', 'boolean'],
        ]);
        $owner = User::findOrFail($data['owner_id']);
        abort_unless($owner->isActive() && $owner->can('leads.view'), 422, 'Choose an active CRM owner.');
        $rule = LeadAssignmentRule::create([...$data, 'organization_id' => $organizationId, 'is_active' => $request->boolean('is_active', true)]);
        AuditEvent::create(['organization_id' => $organizationId, 'actor_id' => $request->user()->id, 'event' => 'lead.assignment_rule.created', 'auditable_type' => LeadAssignmentRule::class, 'auditable_id' => $rule->id, 'new_values' => $rule->only(['source','industry','owner_id','priority','is_active']), 'ip_address' => $request->ip(), 'user_agent' => $request->userAgent()]);

        return back()->with('success', 'Assignment rule added. Lower priority numbers run first.');
    }

    public function destroy(Request $request, LeadAssignmentRule $rule)
    {
        $this->authorize('manageRules', \App\Models\Lead::class);
        abort_unless((int) $rule->organization_id === (int) $request->user()->active_organization_id, 404);
        AuditEvent::create(['organization_id' => $rule->organization_id, 'actor_id' => $request->user()->id, 'event' => 'lead.assignment_rule.deleted', 'auditable_type' => LeadAssignmentRule::class, 'auditable_id' => $rule->id, 'old_values' => $rule->only(['source','industry','owner_id','priority','is_active']), 'ip_address' => $request->ip(), 'user_agent' => $request->userAgent()]);
        $rule->delete();

        return back()->with('success', 'Assignment rule removed.');
    }
}
