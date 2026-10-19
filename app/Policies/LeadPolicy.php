<?php

namespace App\Policies;

use App\Domain\Identity\LeadAccess;
use App\Models\Lead;
use App\Models\User;

class LeadPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isActive() && $user->can('leads.view') && $user->active_organization_id && $user->canAccessOrganization($user->active_organization_id) && app(\App\Domain\Administration\Modules::class)->enabled('crm');
    }

    public function view(User $user, Lead $lead): bool
    {
        return $user->can('leads.view') && app(LeadAccess::class)->allows($user, $lead) && app(\App\Domain\Administration\Modules::class)->enabled('crm');
    }

    public function create(User $user): bool
    {
        return $user->isActive() && $user->can('leads.create') && $user->active_organization_id && $user->canAccessOrganization($user->active_organization_id) && app(\App\Domain\Administration\Modules::class)->enabled('crm');
    }

    public function update(User $user, Lead $lead): bool
    {
        return $user->isActive() && $user->can('leads.update') && ! $lead->archived_at && $this->crmEnabled() && app(LeadAccess::class)->allows($user, $lead);
    }

    public function assign(User $user, Lead $lead): bool
    {
        return $user->isActive() && $user->can('leads.assign') && ! $lead->archived_at && $this->crmEnabled() && app(LeadAccess::class)->allows($user, $lead);
    }

    public function qualify(User $user, Lead $lead): bool
    {
        return $user->isActive() && $user->can('leads.qualify') && ! $lead->archived_at && $this->crmEnabled() && app(LeadAccess::class)->allows($user, $lead);
    }

    public function convert(User $user, Lead $lead): bool
    {
        return $user->isActive() && $user->can('leads.convert') && ! $lead->archived_at && $this->crmEnabled() && app(LeadAccess::class)->allows($user, $lead);
    }

    public function disqualify(User $user, Lead $lead): bool
    {
        return $user->isActive() && $user->can('leads.disqualify') && ! $lead->archived_at && $this->crmEnabled() && app(LeadAccess::class)->allows($user, $lead);
    }

    public function archive(User $user, Lead $lead): bool
    {
        return $user->can('leads.archive') && app(\App\Domain\Administration\Modules::class)->enabled('crm') && app(LeadAccess::class)->allows($user, $lead);
    }

    public function import(User $user): bool
    {
        return $user->isActive() && $user->can('leads.import') && $user->active_organization_id && $user->canAccessOrganization($user->active_organization_id) && app(\App\Domain\Administration\Modules::class)->enabled('crm');
    }

    public function export(User $user): bool
    {
        return $user->isActive() && $user->can('leads.export') && $user->active_organization_id && $user->canAccessOrganization($user->active_organization_id) && app(\App\Domain\Administration\Modules::class)->enabled('crm');
    }

    public function viewReports(User $user): bool
    {
        return $user->isActive() && $user->can('leads.reports.view') && $user->active_organization_id && $user->canAccessOrganization($user->active_organization_id) && app(\App\Domain\Administration\Modules::class)->enabled('crm');
    }

    public function manageRules(User $user): bool
    {
        return $user->isActive() && $user->can('leads.rules.manage') && $user->active_organization_id && $user->canAccessOrganization($user->active_organization_id) && app(\App\Domain\Administration\Modules::class)->enabled('crm');
    }

    public function reviewDuplicates(User $user, Lead $lead): bool
    {
        return $user->isActive() && $user->can('leads.duplicate.review') && ! $lead->archived_at && $this->crmEnabled() && app(LeadAccess::class)->allows($user, $lead);
    }
    private function crmEnabled(): bool
    {
        return app(\App\Domain\Administration\Modules::class)->enabled('crm');
    }
}
