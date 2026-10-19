<?php
namespace App\Policies;
use App\Domain\Administration\Modules;
use App\Domain\Commercial\CommercialAccess;
use App\Models\CommercialQuote;
use App\Models\User;
class CommercialQuotePolicy
{
    public function viewAny(User $user): bool { return $this->active($user, 'quotes.view'); }
    public function view(User $user, CommercialQuote $quote): bool { return $user->isActive() && $user->can('quotes.view') && app(Modules::class)->enabled('contracts') && app(CommercialAccess::class)->allowsQuote($user, $quote); }
    public function create(User $user): bool { return $this->active($user, 'quotes.create') && $user->can('opportunities.view'); }
    public function update(User $user, CommercialQuote $quote): bool { return $user->isActive() && $user->can('quotes.update') && $quote->status === 'draft' && app(Modules::class)->enabled('contracts') && app(CommercialAccess::class)->allowsQuote($user, $quote); }
    public function approve(User $user, CommercialQuote $quote): bool { return $user->isActive() && $user->can('quotes.approve') && app(Modules::class)->enabled('contracts') && app(CommercialAccess::class)->allowsQuote($user, $quote); }
    private function active(User $user, string $permission): bool { return $user->isActive() && $user->active_organization_id && $user->canAccessOrganization($user->active_organization_id) && $user->can($permission) && app(Modules::class)->enabled('contracts'); }
}
