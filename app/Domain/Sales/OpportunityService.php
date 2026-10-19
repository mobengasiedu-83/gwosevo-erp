<?php

namespace App\Domain\Sales;

use App\Models\AuditEvent;
use App\Models\Opportunity;
use App\Models\OpportunityStageHistory;
use App\Models\SalesPipelineStage;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OpportunityService
{
    public const STAGES = [
        ['new', 'New', 10, false, false], ['contacted', 'Contacted', 20, false, false], ['qualified', 'Qualified', 30, false, false],
        ['needs_assessment', 'Needs Assessment', 40, false, false], ['proposal', 'Proposal/Quotation Sent', 60, false, false],
        ['negotiation', 'Negotiation', 75, false, false], ['awaiting_decision', 'Awaiting Customer Decision', 85, false, false],
        ['won', 'Won', 100, true, true], ['lost', 'Lost', 0, true, false], ['on_hold', 'On Hold', 10, false, false],
    ];

    public function ensureStages(int $organizationId): void
    {
        foreach (self::STAGES as $index => [$key, $name, $probability, $closed, $won]) {
            SalesPipelineStage::firstOrCreate(['organization_id' => $organizationId, 'key' => $key], ['name' => $name, 'probability' => $probability, 'sort_order' => $index + 1, 'is_closed' => $closed, 'is_won' => $won]);
        }
    }

    public function create(array $data, User $actor): Opportunity
    {
        return DB::transaction(function () use ($data, $actor) {
            $this->ensureStages((int) $data['organization_id']);
            $stage = SalesPipelineStage::where('organization_id', $data['organization_id'])->where('key', $data['stage'] ?? 'new')->where('is_active', true)->firstOrFail();
            if ($stage->is_closed) throw ValidationException::withMessages(['stage' => 'Create the opportunity in an open stage, then move it through the pipeline.']);
            $opportunity = Opportunity::create([...$data, 'stage' => $stage->key, 'probability' => $stage->probability, 'created_by' => $actor->id, 'updated_by' => $actor->id]);
            $opportunity->update(['reference' => 'OPP-'.str_pad((string) $opportunity->id, 6, '0', STR_PAD_LEFT)]);
            OpportunityStageHistory::create(['opportunity_id' => $opportunity->id, 'from_stage' => null, 'to_stage' => $stage->key, 'changed_by' => $actor->id, 'changed_at' => now()]);
            $this->audit($opportunity, 'opportunity.created', null, ['reference' => $opportunity->reference, 'stage' => $stage->key], $actor);
            return $opportunity->refresh();
        });
    }

    public function update(Opportunity $opportunity, array $data, User $actor): void
    {
        DB::transaction(function () use ($opportunity, $data, $actor) {
            $old = $opportunity->only(['name', 'amount', 'probability', 'expected_close_date', 'stage', 'owner_id', 'service_line_id', 'service_package_id', 'loss_reason', 'nonstandard_terms']);
            $stage = SalesPipelineStage::where('organization_id', $opportunity->organization_id)->where('key', $data['stage'] ?? $opportunity->stage)->where('is_active', true)->firstOrFail();
            if ($stage->is_won) {
                $latestApproval = $opportunity->discountApprovals()->latest()->first();
                if ($opportunity->discountApprovals()->where('status', 'pending')->exists()) throw ValidationException::withMessages(['stage' => 'Pending commercial approvals must be decided before marking the opportunity won.']);
                if (($latestApproval && $latestApproval->status !== 'approved') || (filled($data['nonstandard_terms'] ?? $opportunity->nonstandard_terms) && ! $latestApproval)) throw ValidationException::withMessages(['stage' => 'Nonstandard terms or a declined discount require an approved request before marking the opportunity won.']);
            }
            if (in_array($opportunity->stage, ['won', 'lost'], true) && $stage->key !== $opportunity->stage) throw ValidationException::withMessages(['stage' => 'Closed opportunities cannot be reopened or changed to a different closed stage.']);
            if ($stage->key === 'lost' && blank($data['loss_reason'] ?? $opportunity->loss_reason)) throw ValidationException::withMessages(['loss_reason' => 'Provide a reason before closing an opportunity as lost.']);
            $data['stage'] = $stage->key;
            $data['probability'] = $stage->probability;
            $data['closed_at'] = $stage->is_closed ? ($opportunity->closed_at ?: now()) : null;
            $opportunity->update([...$data, 'updated_by' => $actor->id]);
            if ($old['stage'] !== $stage->key) {
                OpportunityStageHistory::create(['opportunity_id' => $opportunity->id, 'from_stage' => $old['stage'], 'to_stage' => $stage->key, 'changed_by' => $actor->id, 'changed_at' => now()]);
            }
            $this->audit($opportunity, 'opportunity.updated', $old, $opportunity->only(array_keys($old)), $actor);
        });
    }

    public function moveStage(Opportunity $opportunity, string $key, User $actor): void
    {
        $this->update($opportunity, ['stage' => $key, 'loss_reason' => $opportunity->loss_reason], $actor);
    }

    public function addActivity(Opportunity $opportunity, array $data, User $actor): void
    {
        $activity = $opportunity->activities()->create([...$data, 'created_by' => $actor->id]);
        $this->audit($opportunity, 'opportunity.activity.created', null, ['activity_id' => $activity->id, 'type' => $activity->type, 'subject' => $activity->subject], $actor);
    }

    public function requestApproval(Opportunity $opportunity, array $data, User $actor): void
    {
        $approval = $opportunity->discountApprovals()->create([...$data, 'status' => 'pending', 'requested_by' => $actor->id]);
        $this->audit($opportunity, 'opportunity.approval.requested', null, ['approval_id' => $approval->id, 'discount_percent' => $approval->discount_percent], $actor);
    }

    public function decideApproval(Opportunity $opportunity, int $approvalId, string $decision, ?string $note, User $actor): void
    {
        $approval = $opportunity->discountApprovals()->where('status', 'pending')->findOrFail($approvalId);
        if ((int) $approval->requested_by === (int) $actor->id) throw ValidationException::withMessages(['decision' => 'A requester cannot approve or reject their own request.']);
        $approval->update(['status' => $decision, 'decided_by' => $actor->id, 'decision_note' => $note, 'decided_at' => now()]);
        $this->audit($opportunity, 'opportunity.approval.'.$decision, ['approval_id' => $approval->id, 'status' => 'pending'], ['status' => $decision, 'decision_note' => $note], $actor);
    }

    public function addCompetitor(Opportunity $opportunity, array $data, User $actor): void
    {
        $competitor = $opportunity->competitors()->create($data);
        $this->audit($opportunity, 'opportunity.competitor.added', null, ['competitor_id' => $competitor->id, 'name' => $competitor->name], $actor);
    }

    private function audit(Opportunity $opportunity, string $event, ?array $old, array $new, User $actor): void
    {
        AuditEvent::create(['organization_id' => $opportunity->organization_id, 'actor_id' => $actor->id, 'event' => $event, 'auditable_type' => Opportunity::class, 'auditable_id' => $opportunity->id, 'old_values' => $old, 'new_values' => $new, 'ip_address' => request()->ip(), 'user_agent' => request()->userAgent()]);
    }
}
