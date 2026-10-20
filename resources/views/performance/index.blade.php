@extends('layouts.app')

@section('content')
<div class="page-heading"><div><p class="eyebrow">Human Resources / Module 13</p><h1>Performance & appraisals</h1><p class="muted">Run review cycles, gather appraisals, calibrate scores and track development.</p></div></div>
<div class="stats-grid">
    <div class="card"><span class="muted">Cycles</span><strong>{{ $metrics['cycles'] }}</strong></div>
    <div class="card"><span class="muted">Open cycles</span><strong>{{ $metrics['open'] }}</strong></div>
    <div class="card"><span class="muted">Reviews</span><strong>{{ $metrics['reviews'] }}</strong></div>
    <div class="card"><span class="muted">Approved</span><strong>{{ $metrics['completed'] }}</strong></div>
</div>
@if($errors->any())<div class="alert alert-danger"><strong>Please check the form.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<div class="content-grid">
    <section class="card"><div class="card-heading"><div><h2>Review cycles</h2><p class="muted">Opening a cycle adds active employees to it using their current reporting line.</p></div></div>
        @can('performance.manage')
        <form method="post" action="{{ route('performance.cycles.store') }}" class="form-grid">@csrf
            <label>Cycle name<input name="name" required maxlength="180" placeholder="2026 Annual Review"></label>
            <label>Period<select name="period_type" required><option value="monthly">Monthly</option><option value="quarterly">Quarterly</option><option value="biannual">Biannual</option><option value="annual">Annual</option></select></label>
            <label>Starts<input type="date" name="starts_on" required></label><label>Ends<input type="date" name="ends_on" required></label>
            <label>Goal weight (%)<input type="number" name="goal_weight" min="0" max="100" value="60" required></label><label>Competency weight (%)<input type="number" name="competency_weight" min="0" max="100" value="40" required></label>
            <div><button class="button button-primary">Create cycle</button></div>
        </form>
        @endcan
        <div class="table-wrap"><table><thead><tr><th>Cycle</th><th>Period</th><th>Dates</th><th>Status</th><th></th></tr></thead><tbody>
        @forelse($cycles as $cycle)<tr><td>{{ $cycle->name }}</td><td>{{ ucfirst($cycle->period_type) }}</td><td>{{ $cycle->starts_on }} – {{ $cycle->ends_on }}</td><td><span class="badge">{{ str_replace('_',' ',ucfirst($cycle->status)) }}</span></td><td>@if($cycle->status==='draft' && auth()->user()->can('performance.manage'))<form method="post" action="{{ route('performance.cycles.open',$cycle->id) }}">@csrf<button class="button">Open & assign reviews</button></form>@endif</td></tr>@empty<tr><td colspan="5" class="empty-state">No performance cycles yet.</td></tr>@endforelse
        </tbody></table></div>
    </section>
    @can('performance.kpis.manage')<section class="card"><h2>KPI library</h2><p class="muted">Store role or department measures for goal setting.</p><form method="post" action="{{ route('performance.kpis.store') }}" class="stack-form">@csrf
        <label>KPI name<input name="name" required maxlength="180"></label><label>Description<textarea name="description" rows="2"></textarea></label><label>Measure<input name="measure" placeholder="%, count, currency"></label><label>Default target<input name="default_target" type="number" step="0.01" min="0"></label><label>Weight (%)<input name="weight" type="number" min="1" max="100" value="10"></label><button class="button button-primary">Add KPI</button>
    </form><h3>Active measures</h3><ul>@forelse($kpis as $kpi)<li>{{ $kpi->name }} — {{ $kpi->weight }}% @if($kpi->measure)({{ $kpi->measure }})@endif</li>@empty<li class="muted">No KPIs configured.</li>@endforelse</ul></section>@endcan
</div>
<section class="card"><div class="card-heading"><div><h2>Employee reviews</h2><p class="muted">Reviews are private to the employee, assigned manager and authorized HR roles.</p></div></div><div class="table-wrap"><table><thead><tr><th>Employee</th><th>Cycle</th><th>Status</th><th>Self score</th><th>Final score</th><th></th></tr></thead><tbody>
@forelse($reviews as $review)<tr><td>{{ $review->first_name }} {{ $review->last_name }} <small class="muted">{{ $review->employee_number }}</small></td><td>{{ $review->cycle_name }}</td><td><span class="badge">{{ str_replace('_',' ',ucfirst($review->status)) }}</span></td><td>{{ $review->self_score === null ? '—' : $review->self_score.'%' }}</td><td>{{ $review->final_score === null ? '—' : $review->final_score.'%' }}</td><td><a class="button" href="{{ route('performance.reviews.show',$review->id) }}">Open review</a></td></tr>@empty<tr><td colspan="6" class="empty-state">No employee reviews available.</td></tr>@endforelse
</tbody></table></div></section>
@if($feedbackInbox->isNotEmpty())<section class="card"><h2>My 360 feedback requests</h2>@foreach($feedbackInbox as $requestItem)<form method="post" action="{{ route('performance.feedback.respond',$requestItem->id) }}" class="form-grid">@csrf @method('put')<div><strong>{{ $requestItem->first_name }} {{ $requestItem->last_name }}</strong><p class="muted">{{ $requestItem->cycle_name }} · {{ ucfirst($requestItem->relationship) }}</p></div><label>Score<input type="number" name="score" min="0" max="100" required></label><label>Feedback<textarea name="response" required maxlength="5000"></textarea></label><button class="button button-primary">Submit feedback</button></form>@endforeach</section>@endif
@endsection
