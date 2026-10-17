<x-layouts.app>
@include('identity.navigation')
<section class="panel"><h2>Delegate approval authority</h2><p class="help">Maximum delegation period: 90 days. A delegation never grants wider record access or general role permissions. Only approvals supported by installed workflows can consume this authority.</p><form class="grid-form" method="POST" action="{{ route('identity.delegations.store') }}">
@csrf<label>Delegator<select name="delegator_id" required>
@foreach($users as $account)
@if(auth()->user()->hasRole('Administrator') || $account->id===auth()->id())
<option value="{{ $account->id }}">{{ $account->name }}</option>
@endif
@endforeach
</select></label><label>Delegate<select name="delegate_id" required>
@foreach($users as $account)
<option value="{{ $account->id }}">{{ $account->name }}</option>
@endforeach
</select></label><label>Approval permission<select name="permission"><option value="leads.approve">CRM approval (leads.approve)</option></select></label><label>Starts at<input type="datetime-local" name="starts_at" required value="{{ now()->format('Y-m-d\TH:i') }}"></label><label>Ends at<input type="datetime-local" name="ends_at" required></label><label>Reason<textarea name="reason" required maxlength="1000"></textarea></label><div class="wide"><button class="button">Create delegation</button></div></form></section>
<section class="panel"><h2>Delegation history</h2><div class="table-scroll"><table class="admin-table"><thead><tr><th>From → to</th><th>Permission / organization</th><th>Period</th><th>State</th></tr></thead><tbody>
@forelse($delegations as $delegation)
<tr><td>User #{{ $delegation->delegator_id }} → User #{{ $delegation->delegate_id }}</td><td>{{ $delegation->permission }}<p class="help">Organization #{{ $delegation->organization_id }}</p></td><td>{{ $erpSettings->formatDate($delegation->starts_at,true) }} → {{ $erpSettings->formatDate($delegation->ends_at,true) }}</td><td>
@if($delegation->revoked_at)
Revoked
@elseif($delegation->ends_at->isPast())
Expired
@else
{{ $delegation->starts_at->isFuture() ? 'Scheduled' : 'Active' }}
@if(auth()->user()->hasRole('Administrator') || $delegation->delegator_id===auth()->id())
<form method="POST" action="{{ route('identity.delegations.revoke',$delegation) }}" data-confirm="Revoke this approval delegation now?">
@csrf 
@method('DELETE')<button class="button secondary">Revoke</button></form>
@endif
@endif
</td></tr>
@empty
<tr><td colspan="4">No delegations recorded.</td></tr>
@endforelse
</tbody></table></div><div class="row-actions">{{ $delegations->links() }}</div></section>
</x-layouts.app>
