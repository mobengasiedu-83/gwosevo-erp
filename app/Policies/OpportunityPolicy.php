<?php

namespace App\Policies;

use App\Domain\Sales\SalesAccess;
use App\Models\Opportunity;
use App\Models\User;
use App\Domain\Administration\Modules;

class OpportunityPolicy
{
    public function viewAny(User $user): bool { return $this->active($user, 'opportunities.view') && $user->active_organization_id && $user->canAccessOrganization($user->active_organization_id); }
    public function view(User $user, Opportunity $opportunity): bool { return $user->can('opportunities.view') && app(SalesAccess::class)->allows($user, $opportunity); }
    public function create(User $user): bool { return $this->active($user, 'opportunities.create') && $user->active_organization_id && $user->canAccessOrganization($user->active_organization_id); }
    public function update(User $user, Opportunity $opportunity): bool { return $user->can('opportunities.update') && app(SalesAccess::class)->allows($user, $opportunity); }
    public function assign(User $user, Opportunity $opportunity): bool { return $user->can('opportunities.assign') && app(SalesAccess::class)->allows($user, $opportunity); }
    public function approve(User $user, Opportunity $opportunity): bool { return $user->can('opportunities.approve') && $user->id !== $opportunity->created_by && app(SalesAccess::class)->allows($user, $opportunity); }
    public function manageCatalog(User $user): bool { return $this->active($user, 'opportunities.catalog.manage') && $user->active_organization_id && $user->canAccessOrganization($user->active_organization_id); }
    public function manageTargets(User $user): bool { return $this->active($user, 'opportunities.targets.manage') && $user->active_organization_id && $user->canAccessOrganization($user->active_organization_id); }
    public function viewReports(User $user): bool { return $this->active($user, 'opportunities.reports.view') && $user->active_organization_id && $user->canAccessOrganization($user->active_organization_id); }
    private function active(User $user, string $permission): bool { return $user->isActive() && $user->can($permission) && app(Modules::class)->enabled('sales'); }
}
