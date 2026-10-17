<?php

namespace App\Domain\Identity;

use App\Models\ApprovalDelegation;
use App\Models\Lead;
use App\Models\User;

class ApprovalAuthority
{
    public function principal(User $actor, Lead $record, string $permission = 'leads.approve'): ?User
    {
        if (! $actor->isActive() || ! app(LeadAccess::class)->allows($actor, $record)) {
            return null;
        }
        if ($actor->can($permission)) {
            return $actor;
        }
        $delegations = ApprovalDelegation::where('delegate_id', $actor->id)->where('organization_id', $record->organization_id)->where('permission', $permission)->whereNull('revoked_at')->where('starts_at', '<=', now())->where('ends_at', '>', now())->get();
        foreach ($delegations as $delegation) {
            $principal = User::find($delegation->delegator_id);
            if ($principal?->isActive() && $principal->can($permission) && app(LeadAccess::class)->allows($principal, $record)) {
                return $principal;
            }
        }

        return null;
    }
}
