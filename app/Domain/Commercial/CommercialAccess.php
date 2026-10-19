<?php
namespace App\Domain\Commercial;
use App\Domain\Sales\SalesAccess;
use App\Models\CommercialContract;
use App\Models\CommercialQuote;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class CommercialAccess
{
    public function quotes(User $user): Builder
    {
        return CommercialQuote::query()->where('organization_id', $user->active_organization_id)
            ->whereIn('opportunity_id', app(SalesAccess::class)->query($user)->select('opportunities.id'));
    }
    public function contracts(User $user): Builder
    {
        return CommercialContract::query()->where('organization_id', $user->active_organization_id)
            ->whereIn('opportunity_id', app(SalesAccess::class)->query($user)->select('opportunities.id'));
    }
    public function allowsQuote(User $user, CommercialQuote $quote): bool
    {
        return $this->quotes($user)->whereKey($quote->id)->exists();
    }
    public function allowsContract(User $user, CommercialContract $contract): bool
    {
        return $this->contracts($user)->whereKey($contract->id)->exists();
    }
}
