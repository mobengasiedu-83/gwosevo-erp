<x-layouts.app>
@include('identity.navigation')
<section class="panel"><h2>{{ $review->name }}</h2><p class="help">Due {{ $erpSettings->formatDate($review->due_at,true) }} · {{ $review->completed_at ? 'Completed and locked' : 'Open' }}</p></section>
@foreach($items as $item)
<section class="panel"><h2>{{ $users->get($item->user_id)?->name ?: 'User #'.$item->user_id }} <span class="status">{{ $item->decision ?: 'Pending' }}</span></h2><p class="help">{{ $users->get($item->user_id)?->email }}</p><dl><dt>Account status</dt><dd>{{ $item->snapshot['status'] }}</dd><dt>Roles</dt><dd>{{ implode(', ',$item->snapshot['roles']) ?: 'None' }}</dd><dt>Effective permissions</dt><dd>{{ implode(', ',$item->snapshot['effective_permissions']) ?: 'None' }}</dd></dl><details class="record-edit"><summary>Membership scope snapshot</summary><pre class="audit-json">{{ json_encode($item->snapshot['memberships'],JSON_PRETTY_PRINT) }}</pre></details>
@if(!$review->completed_at)
<form class="grid-form" method="POST" action="{{ route('identity.reviews.decide',[$review,$item]) }}">
@csrf 
@method('PUT')<label>Decision<select name="decision"><option value="retain" 
@selected($item->decision==='retain')>Retain access</option><option value="revoke" 
@selected($item->decision==='revoke')>Revoke access</option></select></label><label>Reason / evidence<textarea name="notes" required maxlength="2000">{{ $item->notes }}</textarea></label><div class="wide"><button class="button">Record decision</button></div></form><form class="row-actions" method="POST" action="{{ route('identity.reviews.refresh',[$review,$item]) }}">
@csrf<button class="button secondary">Refresh access snapshot and clear decision</button></form>
@else
<p class="help">{{ $item->notes }} · Decided by user #{{ $item->decided_by }}</p>
@endif
</section>
@endforeach
@if(!$review->completed_at)
@can('administer-system')
<section class="panel"><h2>Complete review</h2><p class="help">All users need decisions. Completion applies revocations and locks the review. If access changed since the snapshot, refresh the affected item and record a new decision.</p><form class="row-actions" method="POST" action="{{ route('identity.reviews.complete',$review) }}" data-confirm="Apply all review decisions and lock this review? Revocation decisions will remove user access.">
@csrf<button class="button">Apply decisions and lock review</button></form></section>
@endcan
@endif
</x-layouts.app>
