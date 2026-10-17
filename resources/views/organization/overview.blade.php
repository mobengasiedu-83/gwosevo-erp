<x-layouts.app :title="$organization->name">
<div class="page-heading"><div><span class="eyebrow">Organization · {{ $organization->code }}</span><h1>{{ $organization->name }}</h1><p>{{ $organization->legalEntity?->name ?? 'Independent operating organization' }} · {{ $organization->timezone }}</p></div><span class="status">{{ $organization->is_active ? 'Active' : 'Inactive' }}</span></div>
@include('organization.nav')
<section class="panel"><div class="section-heading"><h2>Organization chart</h2><form method="GET"><label for="kind">Hierarchy</label><select id="kind" name="kind"><option value="reporting" @selected($kind==='reporting')>Reporting lines</option><option value="approval" @selected($kind==='approval')>Approval lines</option></select><button class="button secondary">View</button></form></div><p>Expand or collapse a person to explore their team. Approval lines use the reporting manager when no separate approver is assigned.</p>
@if($people->isEmpty())
<p>No active people have been assigned. Add memberships in Users & access.</p>
@else
@include('organization.tree',['parent'=>0,'visited'=>[]])
@endif
</section>
<section class="panel"><h2>People & assignments <span class="status">{{ $members->count() }}</span></h2><div class="table-scroll"><table class="admin-table"><thead><tr><th>Person</th><th>Department / team</th><th>Branch / position</th><th>Reporting / approval</th><th>Cost allocation</th>
@if(auth()->user()->hasRole('Administrator'))
<th>Action</th>
@endif
</tr></thead><tbody>
@forelse($members as $member)
<tr><td>{{ $member->user->name }}<br><small>{{ ucfirst($member->user->status) }}</small></td><td>{{ $member->department?->name ?? '—' }}<br><small>{{ $member->team?->name ?? '—' }}</small></td><td>{{ $member->branch?->name ?? '—' }}<br><small>{{ $member->position?->title ?? '—' }}</small></td><td>{{ $member->manager?->name ?? 'No manager' }}<br><small>{{ $member->approvalManager?->name ?? 'Uses reporting line' }}</small></td><td>
@forelse($member->allocations as $allocation)
<div>{{ $allocation->costCentre->code }} · {{ $allocation->percentage }}%</div>
@empty
—
@endforelse
</td>
@if(auth()->user()->hasRole('Administrator'))
<td><a href="{{ route('structure.assignment',[$organization,$member]) }}">Edit assignment</a></td>
@endif
</tr>
@empty
<tr><td colspan="6">No members. Grant organization membership through Users & access.</td></tr>
@endforelse
</tbody></table></div>
@if(auth()->user()->hasRole('Administrator'))
<p><a href="{{ route('identity.users.index') }}">Manage account memberships and access scopes →</a></p>
@endif
</section>
</x-layouts.app>
