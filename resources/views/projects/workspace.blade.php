<x-layouts.app>
<div class="page-heading"><div><p class="eyebrow">Operations &amp; Projects / Module 7</p><h1>{{ $title }}</h1><p class="muted">{{ $organization->name }} · project information is limited to your organization and project access.</p></div></div>

@if($section === 'reports')
<section class="stats-grid" aria-label="Operations report summary">
    @foreach(['Projects' => $summary['projects'], 'Active projects' => $summary['active'], 'Open tasks' => $summary['open_tasks'], 'Overdue tasks' => $summary['overdue_tasks'], 'Milestones' => $summary['milestones'], 'Open risks' => $summary['open_risks'], 'Hours logged' => number_format($summary['hours'], 2)] as $label => $value)
    <div class="card"><span class="muted">{{ $label }}</span><strong>{{ $value }}</strong></div>
    @endforeach
</section>
<section class="card"><h2>Tasks by status</h2><div class="table-wrap"><table><thead><tr><th>Status</th><th>Tasks</th></tr></thead><tbody>@forelse($taskStatuses as $row)<tr><td>{{ str_replace('_', ' ', ucfirst($row->status)) }}</td><td>{{ number_format($row->total) }}</td></tr>@empty<tr><td colspan="2" class="empty-state">No tasks are recorded.</td></tr>@endforelse</tbody></table></div></section>
<section class="card"><h2>Open task workload</h2><div class="table-wrap"><table><thead><tr><th>Assignee</th><th>Open tasks</th></tr></thead><tbody>@forelse($workload as $person)<tr><td>{{ $person->name }}</td><td>{{ number_format($person->task_count) }}</td></tr>@empty<tr><td colspan="2" class="empty-state">No open assigned tasks.</td></tr>@endforelse</tbody></table></div><p class="muted mt-3">These summaries reflect organization records. They do not include budget variance or effort forecasts beyond tracked estimates and time entries.</p></section>
@else
<section class="card"><div class="section-heading"><div><h2>{{ $title }}</h2><p class="muted">Open a project to review related records or take an action allowed by your role.</p></div></div><div class="table-wrap"><table>
@switch($section)
@case('tasks')
<thead><tr><th>Task</th><th>Project</th><th>Owner</th><th>Priority</th><th>Due</th><th>Status</th></tr></thead><tbody>@forelse($records as $record)<tr><td><a href="{{ route('projects.show', $record->project_id) }}">{{ $record->title }}</a></td><td>{{ $record->project_name }}</td><td>{{ $record->owner_name ?: 'Unassigned' }}</td><td>{{ ucfirst($record->priority) }}</td><td>{{ $record->due_on ?: '—' }}</td><td>{{ str_replace('_', ' ', ucfirst($record->status)) }}</td></tr>@empty<tr><td colspan="6" class="empty-state">No tasks are available in your project scope.</td></tr>@endforelse</tbody>
@break
@case('milestones')
<thead><tr><th>Milestone</th><th>Project</th><th>Due</th><th>Status</th><th>Approval</th></tr></thead><tbody>@forelse($records as $record)<tr><td><a href="{{ route('projects.show', $record->project_id) }}">{{ $record->title }}</a></td><td>{{ $record->project_name }}</td><td>{{ $record->due_on ?: '—' }}</td><td>{{ str_replace('_', ' ', ucfirst($record->status)) }}</td><td>{{ $record->requires_approval ? 'Sign-off required' : 'Not required' }}</td></tr>@empty<tr><td colspan="5" class="empty-state">No milestones recorded in your project scope.</td></tr>@endforelse</tbody>
@break
@case('timesheets')
<thead><tr><th>Date</th><th>Project</th><th>Task</th><th>Person</th><th>Hours</th><th>Notes</th></tr></thead><tbody>@forelse($records as $record)<tr><td>{{ $record->work_date }}</td><td><a href="{{ route('projects.show', $record->project_id) }}">{{ $record->project_name }}</a></td><td>{{ $record->task_title }}</td><td>{{ $record->user_name }}</td><td>{{ number_format($record->hours, 2) }}</td><td>{{ $record->notes ?: '—' }}</td></tr>@empty<tr><td colspan="6" class="empty-state">No timesheet entries recorded in your project scope.</td></tr>@endforelse</tbody>
@break
@case('risks')
<thead><tr><th>Risk / issue</th><th>Project</th><th>Probability / impact</th><th>Owner</th><th>Due</th><th>Status</th></tr></thead><tbody>@forelse($records as $record)<tr><td><a href="{{ route('projects.show', $record->project_id) }}">{{ ucfirst($record->kind) }} · {{ $record->title }}</a><small class="block muted">{{ $record->description }}</small></td><td>{{ $record->project_name }}</td><td>{{ ucfirst($record->probability) }} / {{ ucfirst($record->impact) }}</td><td>{{ $record->owner_name ?: 'Unassigned' }}</td><td>{{ $record->due_on ?: '—' }}</td><td>{{ str_replace('_', ' ', ucfirst($record->status)) }}</td></tr>@empty<tr><td colspan="6" class="empty-state">No risks or issues recorded in your project scope.</td></tr>@endforelse</tbody>
@break
@case('deliverables')
<thead><tr><th>Deliverable</th><th>Project</th><th>Owner</th><th>Due</th><th>Work status</th><th>Review status</th></tr></thead><tbody>@forelse($records as $record)<tr><td><a href="{{ route('projects.show', $record->project_id) }}">{{ $record->title }}</a></td><td>{{ $record->project_name }}</td><td>{{ $record->owner_name ?: 'Unassigned' }}</td><td>{{ $record->due_on ?: '—' }}</td><td>{{ str_replace('_', ' ', ucfirst($record->status)) }}</td><td>{{ str_replace('_', ' ', ucfirst($record->approval_status)) }}</td></tr>@empty<tr><td colspan="6" class="empty-state">No deliverables recorded in your project scope.</td></tr>@endforelse</tbody>
@break
@endswitch
</table></div><div class="mt-4">{{ $records->links() }}</div></section>
@endif
</x-layouts.app>
