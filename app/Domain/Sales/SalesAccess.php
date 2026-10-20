<?php

namespace App\Domain\Sales;

use App\Models\Opportunity;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class SalesAccess
{
    public function query(User $user, ?int $organizationId = null): Builder
    {
        $organizationId ??= $user->active_organization_id;
        $query = Opportunity::query()->where('organization_id', $organizationId);
        $organizationId = (int) $organizationId;
        if (! $user->isActive() || ! $organizationId || ! app(\App\Domain\Administration\Modules::class)->enabled('sales')) return $query->whereRaw('1 = 0');
        if ($user->isSuperAdmin()) return $query;
        $membership = $user->organizations()->whereKey($organizationId)->first()?->pivot;
        if (! $membership) return $query->whereRaw('1 = 0');
        if ($membership->scope === 'organization') return $query;
        if ($membership->scope === 'own') return $query->where(fn ($q) => $q->where('owner_id', $user->id)->orWhere(fn ($q) => $q->whereNull('owner_id')->where('created_by', $user->id)));
        $column = match ($membership->scope) { 'team' => 'team_id', 'department' => 'department_id', default => null };
        if (! $column || ! $membership->$column) return $query->whereRaw('1 = 0');
        $members = DB::table('user_organization_memberships')->select('user_id')->where('organization_id', $organizationId)->where($column, $membership->$column);
        return $query->where(fn ($q) => $q->whereIn('owner_id', clone $members)->orWhere(fn ($q) => $q->whereNull('owner_id')->whereIn('created_by', clone $members)));
    }

    public function allows(User $user, Opportunity $opportunity): bool
    {
        return $this->query($user, $opportunity->organization_id)->whereKey($opportunity->id)->exists();
    }
}
