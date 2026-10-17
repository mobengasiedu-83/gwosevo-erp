<x-layouts.app>
@include('identity.navigation')
<section class="panel"><div class="row-actions"><h2>Staff accounts</h2><a class="button" href="{{ route('identity.users.create') }}">Invite user</a></div><form class="row-actions"><label>Search by name or email<input class="admin-input" name="search" value="{{ request('search') }}"></label><button class="button secondary">Search</button></form>
<div class="table-scroll"><table class="admin-table"><thead><tr><th>User</th><th>Status</th><th>Roles</th><th>MFA</th><th>Actions</th></tr></thead><tbody>
@foreach($users as $account)
<tr><td>{{ $account->name }}<p class="help">{{ $account->email }}</p></td><td><span class="status {{ $account->status==='active' ? 'good' : 'warn' }}">{{ ucfirst($account->status) }}</span></td><td>{{ $account->roles->pluck('name')->join(', ') }}</td><td>{{ $account->mfa_confirmed_at ? 'Enabled' : ($account->requiresMfa() ? 'Enrollment required' : 'Optional') }}</td><td><a class="erp-brand" href="{{ route('identity.users.edit',$account) }}">Manage access</a></td></tr>
@endforeach
</tbody></table></div><div class="row-actions">{{ $users->links() }}</div></section>
</x-layouts.app>
