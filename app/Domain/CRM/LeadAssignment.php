<?php

namespace App\Domain\CRM;

use App\Models\Lead;
use App\Models\LeadAssignmentCursor;
use App\Models\LeadAssignmentRule;
use App\Models\OrganizationMembership;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class LeadAssignment
{
    public function choose(Lead $lead): ?User
    {
        $rule = LeadAssignmentRule::query()->where('organization_id', $lead->organization_id)->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('source')->orWhere('source', $lead->source))
            ->where(fn ($q) => $q->whereNull('industry')->orWhere('industry', $lead->industry))
            ->orderBy('priority')->orderByDesc('source')->orderByDesc('industry')->orderBy('id')->first();
        if ($rule && $rule->owner?->isActive() && $rule->owner->canAccessOrganization($lead->organization_id) && $rule->owner->can('leads.view')) {
            return $rule->owner;
        }

        return DB::transaction(function () use ($lead) {
            Organization::whereKey($lead->organization_id)->lockForUpdate()->firstOrFail();
            $cursor = LeadAssignmentCursor::query()->whereKey($lead->organization_id)->lockForUpdate()->first();
            if (! $cursor) {
                LeadAssignmentCursor::create(['organization_id' => $lead->organization_id]);
                Organization::whereKey($lead->organization_id)->lockForUpdate()->firstOrFail();
            $cursor = LeadAssignmentCursor::query()->whereKey($lead->organization_id)->lockForUpdate()->first();
            }
            $owners = OrganizationMembership::query()->with('user')->where('organization_id', $lead->organization_id)
                ->whereHas('user', fn ($q) => $q->where('status', 'active'))->orderBy('user_id')->get()
                ->pluck('user')->filter(fn (User $user) => $user->can('leads.view'))->values();
            if ($owners->isEmpty()) return null;
            $index = $owners->search(fn (User $user) => $user->id === $cursor->last_owner_id);
            $owner = $owners[($index === false ? 0 : ($index + 1) % $owners->count())];
            $cursor->update(['last_owner_id' => $owner->id]);

            return $owner;
        });
    }
}
