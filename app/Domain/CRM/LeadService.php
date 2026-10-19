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
use App\Domain\Sales\OpportunityService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LeadService
{
    public function create(array $data, ?User $actor): Lead
    {
        return DB::transaction(function () use ($data, $actor) {
            $lead = Lead::create([...$data, 'custom_values' => app(CustomFields::class)->validate($data['custom_values'] ?? []), 'reference' => app(NumberSequence::class)->next('lead'), 'phone_normalized' => preg_replace('/\D+/', '', (string) ($data['phone'] ?? '')) ?: null, 'status' => 'new', 'created_by' => $actor?->id, 'updated_by' => $actor?->id]);
            $this->history($lead, null, 'new', 'Lead captured', $actor);
            $this->audit($lead, 'lead.created', null, $lead->only(['reference', 'status', 'source']), $actor);
            $this->refreshScore($lead);
            $duplicates = app(LeadDuplicates::class)->candidates($lead, 1);
            if ($duplicates->isNotEmpty()) {
                $lead->forceFill(['duplicate_of_id' => $duplicates->first()->id])->saveQuietly();
                $this->audit($lead, 'lead.duplicate.possible', null, ['candidate_reference' => $duplicates->first()->reference], $actor);
            }
            if (! $lead->owner_id && ($owner = app(LeadAssignment::class)->choose($lead))) {
                $this->assign($lead, $owner, $actor, 'Automatic round-robin/rule assignment');
            }

            return $lead->refresh();
        });
    }

    public function update(Lead $lead, array $data, User $actor): void
    {
        DB::transaction(function () use ($lead, $data, $actor) {
            $old = $lead->only(['first_name', 'last_name', 'email', 'phone', 'company_name', 'industry', 'interests', 'source', 'next_follow_up_at']);
            $followUpChanged = false;
            if (array_key_exists('next_follow_up_at', $data)) {
                $newFollowUp = blank($data['next_follow_up_at']) ? null : \Illuminate\Support\Carbon::parse($data['next_follow_up_at'])->format('Y-m-d H:i:s');
                $oldFollowUp = $lead->next_follow_up_at?->format('Y-m-d H:i:s');
                $followUpChanged = $newFollowUp !== $oldFollowUp;
            }
            $validatedCustomValues = app(CustomFields::class)->validate($data['custom_values'] ?? []);
            $customValues = array_replace($lead->custom_values ?? [], $validatedCustomValues);
            $lead->update([...$data, ...($followUpChanged ? ['follow_up_reminder_sent_at' => null, 'follow_up_escalated_at' => null] : []), 'phone_normalized' => preg_replace('/\D+/', '', (string) ($data['phone'] ?? $lead->phone)) ?: null, 'custom_values' => $customValues, 'updated_by' => $actor?->id]);
            $this->refreshScore($lead);
            $lead->refresh();
            $candidates = app(LeadDuplicates::class)->candidates($lead, 1);
            if ($candidates->isNotEmpty() && ! $lead->duplicate_reviewed_at) {
                $lead->forceFill(['duplicate_of_id' => $candidates->first()->id])->saveQuietly();
            }
            $this->audit($lead, 'lead.updated', $old, $lead->only(array_keys($old)), $actor);
        });
    }

    public function assign(Lead $lead, User $owner, ?User $actor, string $reason = 'Owner assigned'): void
    {
        if (! $owner->isActive() || ! $owner->canAccessOrganization($lead->organization_id) || ! $owner->can('leads.view')) {
            throw ValidationException::withMessages(['owner_id' => 'Owner must be an active CRM user in the lead organization.']);
        }
        DB::transaction(function () use ($lead, $owner, $actor, $reason) {
            $from = $lead->status;
            $oldOwnerId = $lead->owner_id;
            $lead->update(['owner_id' => $owner->id, 'assigned_at' => now(), 'status' => $from === 'new' ? 'assigned' : $from, 'updated_by' => $actor?->id]);
            if ($from !== $lead->status) $this->history($lead, $from, $lead->status, $reason, $actor);
            $this->audit($lead, 'lead.assigned', ['owner_id' => $oldOwnerId], ['owner_id' => $owner->id, 'reason' => $reason], $actor);
        });
    }

    public function qualify(Lead $lead, array $data, User $actor): void
    {
        if (in_array($lead->status, ['converted', 'disqualified'], true)) throw ValidationException::withMessages(['status' => 'This lead cannot be qualified.']);
        DB::transaction(function () use ($lead, $data, $actor) {
            $from = $lead->status;
            $lead->update([...$data, 'status' => 'qualified', 'qualified_at' => now(), 'updated_by' => $actor?->id]);
            $this->refreshScore($lead);
            $this->history($lead, $from, 'qualified', 'Qualification completed', $actor);
            $this->audit($lead, 'lead.qualified', ['status' => $from], ['status' => 'qualified', 'score' => $lead->fresh()->score], $actor);
        });
    }

    public function addActivity(Lead $lead, array $data, User $actor): void
    {
        DB::transaction(function () use ($lead, $data, $actor) {
            $lead->activities()->create([...$data, 'created_by' => $actor?->id]);
            $this->refreshScore($lead);
            $this->audit($lead, 'lead.activity.created', null, ['type' => $data['type'], 'subject' => $data['subject'], 'due_at' => $data['due_at'] ?? null], $actor);
        });
    }

    public function completeActivity(Lead $lead, int $activityId, User $actor): void
    {
        $activity = $lead->activities()->findOrFail($activityId);
        $activity->update(['completed_at' => now()]);
        $this->refreshScore($lead);
        $this->audit($lead, 'lead.activity.completed', null, ['activity_id' => $activity->id], $actor);
    }

    public function disqualify(Lead $lead, string $reason, User $actor): void
    {
        if (in_array($lead->status, ['converted', 'disqualified'], true)) throw ValidationException::withMessages(['status' => 'This lead is already closed.']);
        DB::transaction(function () use ($lead, $reason, $actor) {
            $from = $lead->status;
            $lead->update(['status' => 'disqualified', 'disqualified_at' => now(), 'disqualification_reason' => $reason, 'updated_by' => $actor?->id]);
            $this->history($lead, $from, 'disqualified', $reason, $actor);
            $this->audit($lead, 'lead.disqualified', ['status' => $from], ['status' => 'disqualified', 'reason' => $reason], $actor);
        });
    }

    public function convert(Lead $lead, User $actor): void
    {
        DB::transaction(function () use ($lead, $actor) {
            $lead = Lead::query()->lockForUpdate()->findOrFail($lead->id);
            if ($lead->status !== 'qualified' || $lead->converted_at) {
                throw ValidationException::withMessages(['status' => 'Only qualified leads can be converted.']);
            }

            $account = Account::firstOrCreate(['organization_id' => $lead->organization_id, 'name' => $lead->company_name ?: $lead->full_name], ['reference' => app(NumberSequence::class)->next('customer'), 'email' => $lead->email, 'phone' => $lead->phone, 'industry' => $lead->industry, 'created_by' => $actor?->id]);
            $contact = Contact::create(['organization_id' => $lead->organization_id, 'account_id' => $account->id, 'first_name' => $lead->first_name, 'last_name' => $lead->last_name, 'email' => $lead->email, 'phone' => $lead->phone, 'created_by' => $actor?->id]);
            $opportunity = app(OpportunityService::class)->create(['organization_id' => $lead->organization_id, 'account_id' => $account->id, 'contact_id' => $contact->id, 'lead_id' => $lead->id, 'name' => ($lead->company_name ?: $lead->full_name).' opportunity', 'amount' => $lead->budget, 'owner_id' => $lead->owner_id, 'stage' => 'new'], $actor);
            $lead->update(['status' => 'converted', 'converted_at' => now(), 'account_id' => $account->id, 'contact_id' => $contact->id, 'opportunity_id' => $opportunity->id, 'updated_by' => $actor?->id]);
            $this->history($lead, 'qualified', 'converted', 'Converted to commercial records', $actor);
            $this->audit($lead, 'lead.converted', null, ['account_id' => $account->id, 'contact_id' => $contact->id, 'opportunity_id' => $opportunity->id], $actor);
        });
    }

    public function archive(Lead $lead, User $actor): void
    {
        $lead->update(['archived_at' => now(), 'updated_by' => $actor?->id]);
        $this->audit($lead, 'lead.archived', null, ['archived_at' => $lead->archived_at], $actor);
    }

    public function restore(Lead $lead, User $actor): void
    {
        $lead->update(['archived_at' => null, 'updated_by' => $actor?->id]);
        $this->audit($lead, 'lead.restored', null, ['archived_at' => null], $actor);
    }

    public function refreshScore(Lead $lead): void
    {
        app(LeadScoring::class)->refresh($lead);
    }

    private function history(Lead $lead, ?string $from, string $to, string $notes, ?User $actor): void
    {
        LeadStatusHistory::create(['lead_id' => $lead->id, 'from_status' => $from, 'to_status' => $to, 'notes' => $notes, 'changed_by' => $actor?->id, 'changed_at' => now()]);
    }

    private function audit(Lead $lead, string $event, ?array $old, array $new, ?User $actor): void
    {
        AuditEvent::create(['organization_id' => $lead->organization_id, 'actor_id' => $actor?->id, 'event' => $event, 'auditable_type' => Lead::class, 'auditable_id' => $lead->id, 'old_values' => $old, 'new_values' => $new, 'ip_address' => request()->ip(), 'user_agent' => request()->userAgent()]);
    }
}
