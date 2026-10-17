<x-layouts.app>
@include('identity.navigation')
@can('administer-system')
<section class="panel"><h2>Start an access review</h2><p class="help">Capture a snapshot of user roles, direct permissions and membership scopes. A reviewer records decisions; an administrator applies revocations and locks the review.</p><form class="grid-form" method="POST" action="{{ route('identity.reviews.store') }}">
@csrf<label>Review name<input name="name" required maxlength="150"></label><label>Reviewer<select name="reviewer_id" required>
@foreach($reviewers as $account)
<option value="{{ $account->id }}">{{ $account->name }}</option>
@endforeach
</select></label><label>Due at<input type="datetime-local" name="due_at" required></label><div class="wide"><button class="button">Create review</button></div></form><p class="help">The scheduler checks daily for a periodic review, every {{ config('identity.review_interval_days') }} days.</p></section>
@endcan
<section class="panel"><h2>Access reviews</h2><table class="admin-table"><thead><tr><th>Review</th><th>Reviewer</th><th>Due</th><th>Status</th></tr></thead><tbody>
@forelse($reviews as $review)
<tr><td><a class="erp-brand" href="{{ route('identity.reviews.show',$review) }}">{{ $review->name }}</a></td><td>User #{{ $review->reviewer_id }}</td><td>{{ $erpSettings->formatDate($review->due_at,true) }}</td><td><span class="status {{ $review->completed_at ? 'good' : 'warn' }}">{{ $review->completed_at ? 'Completed / locked' : ($review->due_at->isPast() ? 'Overdue' : 'Open') }}</span></td></tr>
@empty
<tr><td colspan="4">No access reviews assigned.</td></tr>
@endforelse
</tbody></table><div class="row-actions">{{ $reviews->links() }}</div></section>
</x-layouts.app>
