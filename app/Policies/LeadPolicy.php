<?php

namespace App\Policies;

use App\Domain\Identity\LeadAccess;
use App\Models\Lead;
use App\Models\User;

class LeadPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isActive() && $user->can('leads.view') && $user->active_organization_id && $user->canAccessOrganization($user->active_organization_id);
    }

    public function view(User $user, Lead $lead): bool
    {
        return $user->can('leads.view') && app(LeadAccess::class)->allows($user, $lead);
    }

    public function create(User $user): bool
    {
        return $user->isActive() && $user->can('leads.create') && $user->active_organization_id && $user->canAccessOrganization($user->active_organization_id);
    }

    public function update(User $user, Lead $lead): bool
    {
        return $user->can('leads.update') && app(LeadAccess::class)->allows($user, $lead);
    }

    public function assign(User $user, Lead $lead): bool
    {
        return $user->can('leads.assign') && app(LeadAccess::class)->allows($user, $lead);
    }

    public function qualify(User $user, Lead $lead): bool
    {
        return $user->can('leads.qualify') && app(LeadAccess::class)->allows($user, $lead);
    }

    public function convert(User $user, Lead $lead): bool
    {
        return $user->can('leads.convert') && app(LeadAccess::class)->allows($user, $lead);
    }
}
