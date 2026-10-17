<x-layouts.app>
@include('identity.navigation')
@if(!config('identity.mfa_required'))<div class="notice">Mandatory authenticator enrollment is currently paused. Role requirements remain saved; existing authenticators still protect sign-in.</div>
@endif
<section class="panel"><h2>Create business role</h2><form class="grid-form" method="POST" action="{{ route('identity.roles.store') }}">
@csrf<label>Role name<input name="name" required maxlength="100" value="{{ old('name') }}"></label><label>MFA<select name="requires_mfa"><option value="0">Optional</option><option value="1">Required for this role</option></select></label><fieldset class="wide"><legend>Permissions</legend><div class="permission-options">
@foreach($permissions as $permission)
<label><input type="checkbox" name="permissions[]" value="{{ $permission->name }}"> <x-ui.permission-label :name="$permission->name" /></label>
@endforeach
</div></fieldset><div class="wide"><button class="button">Create role</button></div></form></section>
@foreach($roles as $role)
<section class="panel"><h2>{{ $role->name }} <span class="status">{{ in_array($role->name,App\Http\Controllers\Identity\RoleController::BUILT_IN,true) ? 'Built-in' : 'Business role' }}</span></h2>
@if($role->name==='Administrator')
<p class="help">Protected recovery role. Retains system administration access. {{ config('identity.mfa_required') ? 'Authenticator enrollment is required.' : 'Mandatory authenticator enrollment is currently paused.' }}</p><div class="permission-options">
@foreach($role->permissions as $permission)<span class="status"><x-ui.permission-label :name="$permission->name" /></span>
@endforeach</div>
@else
<details class="record-edit"><summary>Edit permissions</summary><form class="grid-form" method="POST" action="{{ route('identity.roles.update',$role) }}">
@csrf 
@method('PUT')<label>Name<input name="name" value="{{ $role->name }}" required 
@readonly(in_array($role->name,App\Http\Controllers\Identity\RoleController::BUILT_IN,true))></label><label>MFA<select name="requires_mfa"><option value="0" 
@selected(!$role->requires_mfa)>Optional</option><option value="1" 
@selected($role->requires_mfa)>Required</option></select></label><fieldset class="wide"><legend>Permissions</legend><div class="permission-options">
@foreach($permissions as $permission)
<label><input type="checkbox" name="permissions[]" value="{{ $permission->name }}" 
@checked($role->permissions->contains('id',$permission->id))> <x-ui.permission-label :name="$permission->name" /></label>
@endforeach
</div></fieldset><div class="wide"><button class="button">Save role</button></div></form></details>
@if(!in_array($role->name,App\Http\Controllers\Identity\RoleController::BUILT_IN,true))
<form class="row-actions" method="POST" action="{{ route('identity.roles.destroy',$role) }}" data-confirm="Delete this unused business role?">
@csrf 
@method('DELETE')<button class="button secondary">Delete unused role</button></form>
@endif
@endif
</section>
@endforeach
<p class="help">Permissions authorize actions; membership scopes limit the records those actions can affect. Archive, export and approval permissions are registered for CRM workflows; installing a permission does not add a business workflow.</p>
</x-layouts.app>
