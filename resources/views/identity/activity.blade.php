<x-layouts.app>
@include('identity.navigation')
<section class="panel"><h2>Authentication events</h2><div class="table-scroll"><table class="admin-table"><thead><tr><th>Time</th><th>Account</th><th>Event</th><th>Source</th></tr></thead><tbody>
@forelse($logins as $event)
<tr><td>{{ $erpSettings->formatDate($event->created_at,true) }}</td><td>{{ $event->email }}<p class="help">User #{{ $event->user_id }}</p></td><td>{{ $event->event }}<p class="help">{{ $event->method }}</p></td><td>{{ $event->ip_address }}<p class="help">{{ $event->user_agent }}</p></td></tr>
@empty
<tr><td colspan="4">No authentication events.</td></tr>
@endforelse
</tbody></table></div><div class="row-actions">{{ $logins->links() }}</div></section>
<section class="panel"><h2>Access and governance changes</h2><div class="table-scroll"><table class="admin-table"><thead><tr><th>Time / actor</th><th>Action</th><th>Details</th></tr></thead><tbody>
@forelse($changes as $event)
<tr><td>{{ $erpSettings->formatDate($event->created_at,true) }}<p class="help">User #{{ $event->actor_id }}</p></td><td>{{ $event->event }}</td><td><details><summary>Before and after</summary><pre class="audit-json">Before: {{ json_encode($event->old_values,JSON_PRETTY_PRINT) }}
After: {{ json_encode($event->new_values,JSON_PRETTY_PRINT) }}</pre></details></td></tr>
@empty
<tr><td colspan="3">No identity changes yet.</td></tr>
@endforelse
</tbody></table></div><div class="row-actions">{{ $changes->links() }}</div></section>
</x-layouts.app>
