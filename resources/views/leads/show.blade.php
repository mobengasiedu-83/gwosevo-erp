<x-layouts.app>
    <header class="page-heading lead-detail-heading">
        <div>
            <p class="eyebrow">{{ $lead->reference }} · {{ ucfirst($lead->status) }} @if($lead->archived_at) · Archived @endif</p>
            <h1>{{ $lead->full_name }}</h1>
            <p>{{ collect([$lead->company_name, $lead->email, $lead->phone])->filter()->join(' · ') }}</p>
        </div>
        <div class="lead-detail-actions">
            @can('update', $lead)<a class="button button-secondary" href="{{ route('leads.edit', $lead) }}">Edit lead</a>@endcan
            @can('archive', $lead)
                @if($lead->archived_at)
                    <form method="POST" action="{{ route('leads.restore', $lead) }}" class="lead-inline-form">@csrf<button class="button button-secondary">Restore</button></form>
                @else
                    <form method="POST" action="{{ route('leads.archive', $lead) }}" class="lead-inline-form">@csrf<button class="button button-secondary">Archive</button></form>
                @endif
            @endcan
        </div>
    </header>

    <div class="lead-detail-layout">
        <main class="lead-detail-main">
            <section class="card">
                <h2>Lead profile</h2>
                <dl class="lead-profile-grid">
                    <div><dt>Source</dt><dd>{{ config('crm.sources.'.$lead->source, ucfirst($lead->source)) }}{{ $lead->source_detail ? ' · '.$lead->source_detail : '' }}</dd></div>
                    <div><dt>Owner</dt><dd>{{ $lead->owner?->name ?: 'Unassigned' }}</dd></div>
                    <div><dt>Industry</dt><dd>{{ $lead->industry ?: '—' }}</dd></div>
                    <div><dt>Score</dt><dd>{{ $lead->score }}/100</dd></div>
                    <div><dt>Fit</dt><dd>{{ $lead->fit_score }}/100</dd></div>
                    <div><dt>Engagement</dt><dd>{{ $lead->engagement_score }}/100</dd></div>
                    <div><dt>Next follow-up</dt><dd>{{ $erpSettings->formatDate($lead->next_follow_up_at, true) }}</dd></div>
                    <div><dt>Qualification</dt><dd>{{ $lead->authority ?: 'Authority not recorded' }} · {{ $lead->timeline ?: 'Timeline not recorded' }}</dd></div>
                    @foreach($customFields as $field)<div><dt>{{ $field->label }}</dt><dd>{{ $lead->custom_values[$field->key] ?? '—' }}</dd></div>@endforeach
                </dl>
                @if($lead->need)<div class="lead-profile-need"><h3>Business need</h3><p>{{ $lead->need }}</p></div>@endif
            </section>

            @if($lead->duplicate_of_id || $duplicates->isNotEmpty())
                <section class="card lead-duplicate-card">
                    <h2>Possible duplicate review</h2>
                    @if($lead->possibleDuplicateOf)<p>Currently linked to <a href="{{ route('leads.show', $lead->possibleDuplicateOf) }}">{{ $lead->possibleDuplicateOf->reference }} — {{ $lead->possibleDuplicateOf->full_name }}</a></p>@endif
                    @if($duplicates->isNotEmpty())<ul>@foreach($duplicates as $candidate)<li><a href="{{ route('leads.show', $candidate) }}">{{ $candidate->reference }} — {{ $candidate->full_name }} ({{ $candidate->company_name ?: 'no company' }})</a></li>@endforeach</ul>@endif
                    @can('reviewDuplicates', $lead)
                        <form method="POST" action="{{ route('leads.duplicates.review', $lead) }}" class="lead-review-form">@csrf
                            <label>Canonical record<select name="canonical_id"><option value="">Choose canonical record</option>@foreach($duplicates as $candidate)<option value="{{ $candidate->id }}">{{ $candidate->reference }} — {{ $candidate->full_name }}</option>@endforeach</select></label>
                            <button name="is_duplicate" value="1" class="button button-secondary">Confirm duplicate</button><button name="is_duplicate" value="0" class="button button-secondary">Mark unique</button>
                        </form>
                    @endcan
                </section>
            @endif

            <section class="card">
                <h2>Activities and follow-ups</h2>
                <div class="lead-activity-list">
                    @forelse($lead->activities->sortByDesc('created_at') as $activity)
                        <article class="lead-activity">
                            <div class="lead-activity-content">
                                <strong>{{ ucfirst($activity->type) }}: {{ $activity->subject }}</strong>
                                @if($activity->body)<p>{{ $activity->body }}</p>@endif
                                <small>{{ $activity->due_at ? 'Due '.$erpSettings->formatDate($activity->due_at, true) : 'No due date' }} @if($activity->completed_at) · Completed {{ $erpSettings->formatDate($activity->completed_at, true) }} @elseif($activity->due_at && $activity->due_at->isPast()) · Overdue @endif</small>
                            </div>
                            @if($activity->type === 'task' && ! $activity->completed_at)@can('update', $lead)<form method="POST" action="{{ route('leads.activities.complete', [$lead, $activity->id]) }}" class="lead-inline-form">@csrf<button class="button button-secondary">Complete</button></form>@endcan @endif
                        </article>
                    @empty<p class="muted">No activities recorded.</p>@endforelse
                </div>
                @can('update', $lead)
                    <form method="POST" action="{{ route('leads.activities.store', $lead) }}" class="lead-activity-form">@csrf
                        <label>Activity type<select name="type"><option value="note">Note</option><option value="call">Call</option><option value="email">Email</option><option value="meeting">Meeting</option><option value="task">Task / follow-up</option></select></label>
                        <label>Subject<input name="subject" placeholder="Activity subject" required></label>
                        <label class="lead-form-wide">Details<textarea name="body" placeholder="Add activity details"></textarea></label>
                        <label>Due date and time<input name="due_at" type="datetime-local"></label>
                        <button class="button">Add activity</button>
                    </form>
                @endcan
            </section>

            <section class="card">
                <h2>Status history</h2>
                <div class="lead-history-list">
                    @forelse($lead->statusHistory->sortByDesc('changed_at') as $history)
                        <article><strong>{{ ucfirst($history->from_status ?: 'captured') }} → {{ ucfirst($history->to_status) }}</strong><span>{{ $history->actor?->name ?: 'System' }} · {{ $erpSettings->formatDate($history->changed_at, true) }}</span>@if($history->notes)<p>{{ $history->notes }}</p>@endif</article>
                    @empty<p class="muted">No status changes.</p>@endforelse
                </div>
            </section>
        </main>

        <aside class="lead-detail-sidebar">
            @can('assign', $lead)
                <section class="card"><h2>Assign owner</h2><form method="POST" action="{{ route('leads.assign', $lead) }}" class="lead-action-form">@csrf
                    <label for="lead-owner">Lead owner</label><select id="lead-owner" name="owner_id" required><option value="">Select owner</option>@foreach($owners as $owner)<option value="{{ $owner->id }}" @selected($lead->owner_id === $owner->id)>{{ $owner->name }}</option>@endforeach</select>
                    <button class="button">Update owner</button>
                </form></section>
            @endcan

            @if(! in_array($lead->status, ['converted', 'disqualified'], true))
                @can('qualify', $lead)
                    <section class="card"><h2>Qualification</h2><p class="muted lead-card-intro">Fit and engagement scoring updates from profile completeness and recorded activity.</p>
                        <form method="POST" action="{{ route('leads.qualify', $lead) }}" class="lead-action-form">@csrf
                            <label>Budget ({{ $erpSettings->get('currency') }})<input name="budget" type="number" step="0.01" value="{{ $lead->budget }}"></label>
                            <label>Decision authority<input name="authority" value="{{ $lead->authority }}" placeholder="Enter decision maker or role"></label>
                            <label>Business need<textarea name="need" placeholder="Describe the need">{{ $lead->need }}</textarea></label>
                            <label>Timeline<input name="timeline" value="{{ $lead->timeline }}" placeholder="Expected decision timeline"></label>
                            <button class="button">Mark qualified</button>
                        </form>
                    </section>
                @endcan
                @can('disqualify', $lead)
                    <section class="card"><h2>Close as disqualified</h2><form method="POST" action="{{ route('leads.disqualify', $lead) }}" class="lead-action-form">@csrf
                        <label>Reason<input name="reason" maxlength="1000" required placeholder="Enter a reason"></label><button class="button button-secondary">Disqualify</button>
                    </form></section>
                @endcan
            @endif

            @if($lead->status === 'qualified')@can('convert', $lead)<section class="card"><h2>Convert lead</h2><p class="muted lead-card-intro">Create linked account, contact and opportunity records from this qualified lead.</p><form method="POST" action="{{ route('leads.convert', $lead) }}">@csrf<button class="button lead-convert-button">Convert to account, contact and opportunity</button></form></section>@endcan @endif

            @if($lead->opportunity)<section class="card"><h2>Conversion</h2><dl class="lead-conversion-list"><div><dt>Account</dt><dd>{{ $lead->account?->reference ?? $lead->account?->name }}</dd></div><div><dt>Contact</dt><dd>{{ $lead->contact?->full_name ?? $lead->contact?->first_name }}</dd></div><div><dt>Opportunity</dt><dd>{{ $lead->opportunity->name }}</dd></div></dl></section>@endif
        </aside>
    </div>
</x-layouts.app>
