<?php

namespace App\Domain\CRM;

use App\Domain\Administration\CustomFields;
use App\Domain\Administration\NumberSequence;
use App\Models\Account;
use App\Models\AuditEvent;
use App\Models\Contact;
use App\Models\Lead;
use App\Models\LeadStatusHistory;
use App\Models\Opportunity;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LeadService
{
    public function create(array $data, User $actor): Lead
    {
        return DB::transaction(function () use ($data, $actor) {
            $lead = Lead::create([...$data, 'custom_values' => app(CustomFields::class)->validate($data['custom_values'] ?? []), 'reference' => app(NumberSequence::class)->next('lead'), 'status' => 'new', 'created_by' => $actor->id, 'updated_by' => $actor->id]);
            $this->history($lead, null, 'new', 'Lead captured', $actor);
            $this->audit($lead, 'lead.created', null, $lead->only(['reference', 'status']), $actor);

            return $lead;
        });
    }

    public function assign(Lead $lead, User $owner, User $actor): void
    {
        if (! $owner->canAccessOrganization($lead->organization_id)) {
            throw ValidationException::withMessages(['owner_id' => 'Owner must belong to the lead organization.']);
        }
        DB::transaction(function () use ($lead, $owner, $actor) {
            $from = $lead->status;
            $lead->update(['owner_id' => $owner->id, 'assigned_at' => now(), 'status' => $from === 'new' ? 'assigned' : $from, 'updated_by' => $actor->id]);
            if ($from !== $lead->status) {
                $this->history($lead, $from, $lead->status, "Assigned to {$owner->name}", $actor);
            } $this->audit($lead, 'lead.assigned', null, ['owner_id' => $owner->id], $actor);
        });
    }

    public function qualify(Lead $lead, array $data, User $actor): void
    {
        if (in_array($lead->status, ['converted', 'disqualified'], true)) {
            throw ValidationException::withMessages(['status' => 'This lead cannot be qualified.']);
        }
        DB::transaction(function () use ($lead, $data, $actor) {
            $from = $lead->status;
            $lead->update([...$data, 'status' => 'qualified', 'qualified_at' => now(), 'updated_by' => $actor->id]);
            $this->history($lead, $from, 'qualified', 'Qualification completed', $actor);
            $this->audit($lead, 'lead.qualified', ['status' => $from], ['status' => 'qualified'], $actor);
        });
    }

    public function convert(Lead $lead, User $actor): void
    {
        if ($lead->status !== 'qualified') {
            throw ValidationException::withMessages(['status' => 'Only qualified leads can be converted.']);
        }
        DB::transaction(function () use ($lead, $actor) {
            $account = Account::firstOrCreate(['organization_id' => $lead->organization_id, 'name' => $lead->company_name ?: $lead->full_name], ['reference' => app(NumberSequence::class)->next('customer'), 'email' => $lead->email, 'phone' => $lead->phone, 'industry' => $lead->industry, 'created_by' => $actor->id]);
            $contact = Contact::create(['organization_id' => $lead->organization_id, 'account_id' => $account->id, 'first_name' => $lead->first_name, 'last_name' => $lead->last_name, 'email' => $lead->email, 'phone' => $lead->phone, 'created_by' => $actor->id]);
            $opportunity = Opportunity::create(['organization_id' => $lead->organization_id, 'account_id' => $account->id, 'contact_id' => $contact->id, 'lead_id' => $lead->id, 'name' => ($lead->company_name ?: $lead->full_name).' opportunity', 'amount' => $lead->budget, 'owner_id' => $lead->owner_id, 'created_by' => $actor->id]);
            $lead->update(['status' => 'converted', 'converted_at' => now(), 'account_id' => $account->id, 'contact_id' => $contact->id, 'opportunity_id' => $opportunity->id, 'updated_by' => $actor->id]);
            $this->history($lead, 'qualified', 'converted', 'Converted to commercial records', $actor);
            $this->audit($lead, 'lead.converted', null, ['opportunity_id' => $opportunity->id], $actor);
        });
    }

    private function history(Lead $lead, ?string $from, string $to, string $notes, User $actor): void
    {
        LeadStatusHistory::create(['lead_id' => $lead->id, 'from_status' => $from, 'to_status' => $to, 'notes' => $notes, 'changed_by' => $actor->id, 'changed_at' => now()]);
    }

    private function audit(Lead $lead, string $event, ?array $old, array $new, User $actor): void
    {
        AuditEvent::create(['organization_id' => $lead->organization_id, 'actor_id' => $actor->id, 'event' => $event, 'auditable_type' => Lead::class, 'auditable_id' => $lead->id, 'old_values' => $old, 'new_values' => $new, 'ip_address' => request()->ip(), 'user_agent' => request()->userAgent()]);
    }
}
