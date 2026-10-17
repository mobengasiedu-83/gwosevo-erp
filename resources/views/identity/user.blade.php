<x-layouts.app>
@include('identity.navigation')
@php
$oldMemberships = session()->hasOldInput('memberships') ? collect(old('memberships'))->keyBy('organization_id') : null;
@endphp
<section class="panel"><h2>{{ $account->exists ? 'Manage '.$account->name : 'Invite a staff member' }}</h2><p class="help">Passwords are set by the user through a one-time invitation. This screen cannot change an existing email or password.</p>
<form class="grid-form" method="POST" action="{{ $account->exists ? route('identity.users.update',$account) : route('identity.users.store') }}">
@csrf
@if($account->exists)
@method('PUT')
@endif
<label>Name<input name="name" value="{{ old('name',$account->name) }}" required maxlength="150"></label>
@if(!$account->exists)
<label>Email<input type="email" name="email" value="{{ old('email') }}" required maxlength="255"></label>
@else
<div><p class="field-label">Email</p><p class="help">{{ $account->email }}</p></div>
<label>Account status<select name="status">
@foreach(['invited','active','suspended','inactive'] as $status)
<option value="{{ $status }}" 
@selected(old('status',$account->status)===$status)>{{ ucfirst($status) }}</option>
@endforeach
</select></label>
@endif
<label>Active organization<select name="active_organization_id" required>
@foreach($organizations as $organization)
<option value="{{ $organization->id }}" 
@selected((int)old('active_organization_id',$account->active_organization_id)===$organization->id)>{{ $organization->name }}</option>
@endforeach
</select></label>
<label>MFA requirement<select name="require_mfa"><option value="0" 
@selected(!old('require_mfa',$account->require_mfa))>Optional unless the role requires it</option><option value="1" 
@selected(old('require_mfa',$account->require_mfa))>Required</option></select></label>
<label>Microsoft Entra object ID<input name="entra_object_id" value="{{ old('entra_object_id',$account->entra_object_id) }}" placeholder="Object ID of this user in your Entra tenant"><span class="help">Explicit account linking. Tenant: {{ config('identity.entra.tenant_id') ?: 'not configured' }}. Leave blank for local sign-in only.</span></label>
<fieldset class="wide"><legend class="field-label">Roles</legend><div class="permission-options">
@foreach($roles as $role)
<label><input type="checkbox" name="roles[]" value="{{ $role->name }}" 
@checked(in_array($role->name,old('roles',$account->roles->pluck('name')->all())))> {{ $role->name }}</label>
@endforeach
</div></fieldset>
<details class="wide"><summary>Additional direct permissions</summary><p class="help">These add to role permissions; they do not deny permissions granted by roles.</p><div class="permission-options">
@foreach($permissions as $permission)
<label><input type="checkbox" name="permissions[]" value="{{ $permission->name }}" 
@checked(in_array($permission->name,old('permissions',$account->permissions->pluck('name')->all())))> <x-ui.permission-label :name="$permission->name" /></label>
@endforeach
</div></details>
<fieldset class="wide"><legend class="field-label">Organization membership and data scope</legend><p class="help">Choose each organization this user belongs to. Team and department scopes require a matching assignment. Own scope shows assigned records and unassigned records created by the user.</p>
@foreach($organizations as $index=>$organization)
@php
$membership = $memberships->get($organization->id)?->pivot;
if ($oldMemberships !== null) {
    $oldMembership = $oldMemberships->get($organization->id);
    $membership = $oldMembership ? (object)array_replace(['department_id'=>null,'team_id'=>null],$oldMembership) : null;
}
$membershipEnabled = $membership || (!$account->exists && !session()->hasOldInput() && $loop->first);
@endphp
<div class="panel" style="margin-top:12px;padding:16px">
<label class="membership-toggle"><input type="checkbox" 
@checked($membershipEnabled) onchange="for (const control of this.closest('div.panel').querySelectorAll('[data-membership]')) control.disabled = !this.checked"> {{ $organization->name }}</label>
<input data-membership type="hidden" name="memberships[{{ $index }}][organization_id]" value="{{ $organization->id }}" 
@disabled(!$membershipEnabled)>
<div class="grid-form">
<label>Data scope<select data-membership name="memberships[{{ $index }}][scope]" 
@disabled(!$membershipEnabled)>
@foreach(['own','team','department','organization'] as $scope)
<option value="{{ $scope }}" 
@selected(($membership?->scope ?? 'own')===$scope)>{{ ucfirst($scope) }}</option>
@endforeach
</select></label>
<label>Department<select data-membership name="memberships[{{ $index }}][department_id]" 
@disabled(!$membershipEnabled)><option value="">None</option>
@foreach($departments->where('organization_id',$organization->id) as $department)
<option value="{{ $department->id }}" 
@selected((int)$membership?->department_id===$department->id)>{{ $department->name }}</option>
@endforeach
</select></label>
<label>Team<select data-membership name="memberships[{{ $index }}][team_id]" 
@disabled(!$membershipEnabled)><option value="">None</option>
@foreach($teams->where('organization_id',$organization->id) as $team)
<option value="{{ $team->id }}" 
@selected((int)$membership?->team_id===$team->id)>{{ $team->name }} ({{ $team->department?->name ?: 'No department' }})</option>
@endforeach
</select></label></div></div>
@endforeach
</fieldset>
<div class="wide"><button class="button">{{ $account->exists ? 'Save user access' : 'Create account and send invitation' }}</button></div></form></section>
@if($account->exists)

@if($account->mfa_confirmed_at && $account->id!==auth()->id())
<section class="panel"><h2>Reset lost authenticator</h2><p class="help">Verify the staff member’s identity through your approved process. This forces new enrollment and revokes their sessions; their password stays unchanged.</p><form class="grid-form" method="POST" action="{{ route('identity.users.mfa-reset',$account) }}">
@csrf
<label>Your administrator password<input type="password" name="current_password" required autocomplete="current-password"></label><label>Your authenticator or recovery code<input name="code" required autocomplete="one-time-code"></label><label class="wide">Reason / identity verification evidence<textarea name="reason" required maxlength="1000"></textarea></label><div class="wide"><button class="button secondary">Reset authenticator and require enrollment</button></div></form></section>
@endif
<section class="panel"><h2>Account actions</h2><div class="row-actions">
@if($account->status==='invited')
<form method="POST" action="{{ route('identity.users.resend',$account) }}">
@csrf<button class="button">Resend invitation</button></form>
@endif
<form method="POST" action="{{ route('identity.users.revoke',$account) }}" data-confirm="End all sessions and revoke API tokens for this user?">
@csrf 
@method('DELETE')<button class="button secondary">Revoke all sessions and API tokens</button></form>
</div><p class="help">Access changes end existing sessions. The final active administrator is protected from deactivation or role removal.</p></section>
@endif
</x-layouts.app>
