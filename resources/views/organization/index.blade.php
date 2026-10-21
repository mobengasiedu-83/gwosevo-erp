<x-layouts.app :title="$section === 'departments' ? 'Departments' : 'Organization'">
<div class="page-heading"><div><span class="eyebrow">Administration</span><h1>{{ $section === 'departments' ? 'Departments' : 'Organization' }}</h1><p>{{ $section === 'departments' ? 'Browse department directories for each organization.' : 'Manage organizations, companies and their operational structure.' }}</p></div></div>
@include('organization.nav')
<div class="structure-cards">
@forelse($organizations as $organization)
<a class="panel structure-card" href="{{ $section === 'departments' ? route('structure.units.index', [$organization->id, 'departments']) : route('structure.overview',$organization) }}"><span class="eyebrow">{{ $organization->code }}</span><h2>{{ $organization->name }}</h2><p>{{ $organization->legalEntity?->name ?? 'Legal entity not assigned' }}</p><span class="status">{{ $organization->is_active ? 'Active' : 'Inactive' }}</span><p>{{ $section === 'departments' ? 'Open department directory →' : 'Explore people, teams and reporting lines →' }}</p></a>
@empty
<div class="panel"><h2>No organizations available</h2><p>An administrator can create organizations and grant membership through Users & access.</p></div>
@endforelse
</div>
</x-layouts.app>
