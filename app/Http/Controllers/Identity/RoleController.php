<?php

namespace App\Http\Controllers\Identity;

use App\Domain\Identity\IdentityAudit;
use App\Domain\Identity\SessionRegistry;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleController extends Controller
{
    public const BUILT_IN = ['Administrator', 'Sales', 'Operations', 'Human Resources', 'Finance', 'Support', 'Read Only'];

    public function index(Request $request)
    {
        $section = $request->query('section') === 'permissions' ? 'permissions' : 'roles';

        return view('identity.roles', ['section' => $section, 'roles' => Role::where('guard_name', 'web')->with('permissions')->orderBy('name')->get(), 'permissions' => Permission::where('guard_name', 'web')->orderBy('name')->get()]);
    }

    public function save(Request $request, ?Role $role = null)
    {
        abort_if($role?->name === 'Administrator', 422, 'Administrator is the protected recovery role.');
        $data = $request->validate(['name' => ['required', 'string', 'max:100', Rule::unique('roles')->where('guard_name', 'web')->ignore($role?->id)], 'permissions' => ['nullable', 'array'], 'permissions.*' => ['string', Rule::exists('permissions', 'name')->where('guard_name', 'web')], 'requires_mfa' => ['required', 'boolean']]);
        if ($role && in_array($role->name, self::BUILT_IN, true)) {
            $data['name'] = $role->name;
        }
        DB::transaction(function () use ($role, $data) {
            $old = $role ? ['name' => $role->name, 'permissions' => $role->permissions->pluck('name')->all(), 'requires_mfa' => (bool) $role->requires_mfa] : null;
            $role ??= new Role;
            $role->fill(['name' => $data['name'], 'guard_name' => 'web'])->forceFill(['requires_mfa' => (bool) $data['requires_mfa']])->save();
            $role->syncPermissions($data['permissions'] ?? []);
            foreach (User::role($role->name)->orderBy('id')->lockForUpdate()->get() as $user) {
                app(SessionRegistry::class)->revokeAll($user);
            }
            IdentityAudit::change('role.saved', 0, $old, ['role_id' => $role->id, 'name' => $role->name, 'permissions' => $data['permissions'] ?? [], 'requires_mfa' => (bool) $data['requires_mfa']]);
        });
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return back()->with('success', 'Role saved. Assigned users must sign in again.');
    }

    public function destroy(Role $role)
    {
        abort_if(in_array($role->name, self::BUILT_IN, true), 422, 'Built-in roles cannot be deleted.');
        DB::transaction(function () use ($role) {
            $role = Role::whereKey($role->id)->lockForUpdate()->firstOrFail();
            abort_if(User::role($role->name)->exists(), 422, 'Remove this role from its users before deleting it.');
            IdentityAudit::change('role.deleted', 0, ['role_id' => $role->id, 'name' => $role->name], null);
            $role->delete();
        });

        return back()->with('success', 'Unused role deleted.');
    }
}
