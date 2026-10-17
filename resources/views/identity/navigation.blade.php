<div class="admin-heading"><p>Administration / Module 02</p><h1>Identity and access</h1><p>Manage staff accounts, permissions and access governance.</p></div>
<nav class="admin-tabs" aria-label="Identity sections">
@can('administer-system')
<a href="{{ route('identity.users.index') }}" 
@if(request()->routeIs('identity.users.*')) aria-current="page" 
@endif><x-ui.icon name="users" /><span>Users</span></a><a href="{{ route('identity.roles.index') }}" 
@if(request()->routeIs('identity.roles.*')) aria-current="page" 
@endif><x-ui.icon name="shield" /><span>Roles & permissions</span></a><a href="{{ route('identity.activity') }}" 
@if(request()->routeIs('identity.activity')) aria-current="page" 
@endif><x-ui.icon name="activity" /><span>Audit activity</span></a><a href="{{ route('identity.integrations') }}" 
@if(request()->routeIs('identity.integrations')) aria-current="page" 
@endif><x-ui.icon name="key" /><span>SSO & email</span></a>
@endcan
<a href="{{ route('identity.delegations.index') }}" 
@if(request()->routeIs('identity.delegations.*')) aria-current="page" 
@endif><x-ui.icon name="arrow" /><span>Approval delegation</span></a><a href="{{ route('identity.reviews.index') }}" 
@if(request()->routeIs('identity.reviews.*')) aria-current="page" 
@endif><x-ui.icon name="file" /><span>Access reviews</span></a><a href="{{ route('security.index') }}" 
@if(request()->routeIs('security.*')) aria-current="page" 
@endif><x-ui.icon name="shield" /><span>My security</span></a>
</nav>
