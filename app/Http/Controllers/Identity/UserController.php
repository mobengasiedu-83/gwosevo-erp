<?php

namespace App\Http\Controllers\Identity;

use App\Domain\Identity\IdentityAudit;
use App\Domain\Identity\Invitations;
use App\Domain\Identity\Mfa;
use App\Domain\Identity\SessionRegistry;
use App\Domain\Identity\UserAccess;
use App\Domain\Organization\Assignments;
use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Organization;
use App\Models\Team;
use App\Models\User;
use App\Models\UserInvitation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index(Request $request)
    {
        return view('identity.users', ['users' => User::with('roles')->when($request->filled('search'), fn ($q) => $q->where(fn ($q) => $q->where('name', 'like', '%'.$request->input('search').'%')->orWhere('email', 'like', '%'.$request->input('search').'%')))->orderBy('name')->paginate(25)->withQueryString()]);
    }

    public function create()
    {
        return $this->form(new User);
    }

    public function edit(User $user)
    {
        return $this->form($user);
    }

    private function form(User $user)
    {
        return view('identity.user', ['account' => $user, 'roles' => Role::where('guard_name', 'web')->orderBy('name')->get(), 'permissions' => Permission::where('guard_name', 'web')->orderBy('name')->get(), 'organizations' => Organization::where('is_active', true)->get(), 'departments' => Department::where('is_active', true)->get(), 'teams' => Team::where('is_active', true)->get(), 'memberships' => $user->exists ? $user->organizations->keyBy('id') : collect()]);
    }

    public function store(Request $request, Invitations $invitations)
    {
        $request->merge(['email' => mb_strtolower(trim((string) $request->input('email')))]);
        $data = $this->data($request);
        $request->validate(['name' => ['required', 'string', 'max:150'], 'email' => ['required', 'email', 'max:255', Rule::unique('users')]]);
        $user = DB::transaction(function () use ($request, $data) {
            $user = User::create(['name' => $request->input('name'), 'email' => mb_strtolower($request->input('email')), 'password' => Str::random(64), 'status' => 'invited', 'active_organization_id' => $data['active_organization_id']]);
            $this->apply($user, $data);
            IdentityAudit::change('user.invited', $user->id, null, app(UserAccess::class)->snapshot($user));

            return $user;
        });
        // On delivery failure the invited account remains visible and can be resent.
        try {
            $invitations->send($user);
        } catch (ValidationException $exception) {
            return to_route('identity.users.edit', $user)->withErrors($exception->errors());
        }

        return to_route('identity.users.edit', $user)->with('success', 'User invited.');
    }

    public function update(Request $request, User $user, UserAccess $access)
    {
        $data = $this->data($request);
        $request->validate(['name' => ['required', 'string', 'max:150'], 'status' => ['required', Rule::in(['active', 'suspended', 'inactive', 'invited'])]]);
        if ($user->status === 'invited' && $request->input('status') === 'active') {
            throw ValidationException::withMessages(['status' => 'The invited user must accept their invitation to activate the account.']);
        }
        if ($user->status !== 'invited' && $request->input('status') === 'invited') {
            throw ValidationException::withMessages(['status' => 'Existing accounts cannot return to invitation status.']);
        }
        DB::transaction(function () use ($request, $user, $data, $access) {
            Organization::orderBy('id')->lockForUpdate()->get(['id']);
            $locked = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $access->guardAdministrator($locked, $request->input('status'), $data['roles']);
            $old = $access->snapshot($locked);
            $locked->update(['name' => $request->input('name'), 'status' => $request->input('status'), 'active_organization_id' => $data['active_organization_id']]);
            $this->apply($locked, $data);
            app(SessionRegistry::class)->revokeAll($locked);
            if ($locked->status !== 'invited') {
                UserInvitation::where('user_id', $locked->id)->whereNull('accepted_at')->update(['revoked_at' => now()]);
            }
            IdentityAudit::change('user.access_updated', $locked->id, $old, $access->snapshot($locked));
        });

        return back()->with('success', 'User access saved. Existing sessions were revoked.');
    }

    public function resend(User $user, Invitations $invitations)
    {
        $invitations->send($user);

        return back()->with('success', 'A fresh invitation was sent. Previous invitations are invalid.');
    }

    public function revoke(User $user)
    {
        DB::transaction(function () use ($user) {
            $locked = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            app(SessionRegistry::class)->revokeAll($locked);
            IdentityAudit::change('sessions.revoked', $user->id, null, ['all_sessions' => true]);
        });

        return back()->with('success', 'All user sessions and API tokens revoked.');
    }

    public function resetMfa(Request $request, User $user, Mfa $mfa)
    {
        abort_if($user->id === $request->user()->id, 422, 'Use your recovery codes for your own account.');
        $data = $request->validate(['current_password' => ['required', 'current_password:web'], 'code' => ['required', 'string', 'max:100'], 'reason' => ['required', 'string', 'max:1000']]);
        if (! $mfa->verify($request->user(), $data['code'])) {
            throw ValidationException::withMessages(['code' => 'A valid administrator authenticator or recovery code is required.']);
        }
        DB::transaction(function () use ($user, $data) {
            $locked = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $locked->forceFill(['mfa_secret' => null, 'mfa_confirmed_at' => null, 'mfa_last_used' => null, 'mfa_recovery_codes' => null, 'require_mfa' => true])->save();
            app(SessionRegistry::class)->revokeAll($locked);
            IdentityAudit::change('mfa.admin_reset', $locked->id, null, ['reason' => $data['reason'], 'enrollment_required' => true]);
        });

        return back()->with('success', 'MFA reset and sessions revoked. The user must enroll again. Their password is unchanged.');
    }

    private function data(Request $request): array
    {
        $data = $request->validate([
            'roles' => ['required', 'array', 'min:1'], 'roles.*' => ['string', Rule::exists('roles', 'name')->where('guard_name', 'web')],
            'permissions' => ['nullable', 'array'], 'permissions.*' => ['string', Rule::exists('permissions', 'name')->where('guard_name', 'web')],
            'active_organization_id' => ['required', 'integer', Rule::exists('organizations', 'id')->where('is_active', true)],
            'memberships' => ['required', 'array', 'min:1'], 'memberships.*.organization_id' => ['required', 'integer', 'distinct', Rule::exists('organizations', 'id')->where('is_active', true)],
            'memberships.*.scope' => ['required', Rule::in(['own', 'team', 'department', 'organization'])],
            'memberships.*.department_id' => ['nullable', 'integer'], 'memberships.*.team_id' => ['nullable', 'integer'],
            'require_mfa' => ['required', 'boolean'], 'entra_object_id' => ['nullable', 'uuid', Rule::unique('users')->where('entra_tenant_id', config('identity.entra.tenant_id'))->ignore($request->route('user')?->id)],
        ]);
        if (! in_array((int) $data['active_organization_id'], array_map('intval', array_column($data['memberships'], 'organization_id')), true)) {
            throw ValidationException::withMessages(['active_organization_id' => 'The active organization must have a membership.']);
        }
        foreach ($data['memberships'] as $membership) {
            $department = ! empty($membership['department_id']) ? Department::where('is_active', true)->where('organization_id', $membership['organization_id'])->find($membership['department_id']) : null;
            $team = ! empty($membership['team_id']) ? Team::where('is_active', true)->where('organization_id', $membership['organization_id'])->find($membership['team_id']) : null;
            if ((! empty($membership['department_id']) && ! $department) || (! empty($membership['team_id']) && ! $team) || ($team && (int) $team->department_id !== (int) ($department?->id)) || ($membership['scope'] === 'team' && ! $team) || ($membership['scope'] === 'department' && ! $department)) {
                throw ValidationException::withMessages(['memberships' => 'Select a valid department/team within the same organization for the chosen scope.']);
            }
        }
        if (! empty($data['entra_object_id']) && ! Str::isUuid(config('identity.entra.tenant_id'))) {
            throw ValidationException::withMessages(['entra_object_id' => 'Configure the Entra tenant before linking Microsoft identities.']);
        }

        return $data;
    }

    private function apply(User $user, array $data): void
    {
        app(Assignments::class)->guardMembershipChanges($user, $data['memberships']);
        $memberships = [];
        foreach ($data['memberships'] as $membership) {
            $memberships[$membership['organization_id']] = ['scope' => $membership['scope'], 'department_id' => $membership['department_id'] ?? null, 'team_id' => $membership['team_id'] ?? null];
        }
        $user->organizations()->sync($memberships);
        $user->syncRoles($data['roles']);
        $user->syncPermissions($data['permissions'] ?? []);
        $user->forceFill(['require_mfa' => (bool) $data['require_mfa'], 'entra_tenant_id' => empty($data['entra_object_id']) ? null : config('identity.entra.tenant_id'), 'entra_object_id' => $data['entra_object_id'] ?? null])->save();
    }
}
