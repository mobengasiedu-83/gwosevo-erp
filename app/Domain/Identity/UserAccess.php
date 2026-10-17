<?php

namespace App\Domain\Identity;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UserAccess
{
    public function snapshot(User $user): array
    {
        $user->unsetRelation('roles')->unsetRelation('permissions')->unsetRelation('organizations');

        return [
            'name' => $user->name, 'email' => $user->email,
            'entra_tenant_id' => $user->entra_tenant_id, 'entra_object_id' => $user->entra_object_id,
            'mfa_required' => $user->requiresMfa(),
            'status' => $user->status, 'require_mfa' => (bool) $user->require_mfa,
            'roles' => $user->roles()->orderBy('id')->pluck('name')->all(),
            'permissions' => $user->permissions()->orderBy('id')->pluck('name')->all(),
            'effective_permissions' => $user->getAllPermissions()->pluck('name')->sort()->values()->all(),
            'memberships' => DB::table('user_organization_memberships')->where('user_id', $user->id)->orderBy('organization_id')->get(['organization_id', 'department_id', 'team_id', 'scope', 'branch_id', 'position_id', 'manager_id', 'approval_manager_id'])->map(fn ($row) => (array) $row)->all(),
        ];
    }

    public function guardAdministrator(User $user, string $status, array $roles): void
    {
        if ($user->hasRole('Administrator') && ($status !== 'active' || ! in_array('Administrator', $roles, true))) {
            // Lock all current administrators in ID order to serialize removals.
            $admins = User::role('Administrator')->where('status', 'active')->orderBy('id')->lockForUpdate()->get();
            if ($admins->where('id', '!=', $user->id)->isEmpty()) {
                throw ValidationException::withMessages(['roles' => 'The last active administrator cannot lose access.']);
            }
        }
        if ($user->id === auth()->id() && ($status !== 'active' || ! in_array('Administrator', $roles, true))) {
            throw ValidationException::withMessages(['roles' => 'Use another administrator to change your own administrator access.']);
        }
    }
}
