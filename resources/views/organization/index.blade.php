<x-layouts.app title="Organization structure">
<div class="page-heading"><div><span class="eyebrow">Module 03 · Organization</span><h1>Organization structure</h1><p>Connect your companies, people and operational units.</p></div></div>
@include('organization.nav')
<div class="structure-cards">
@forelse($organizations as $organization)
<a class="panel structure-card" href="{{ route('structure.overview',$organization) }}"><span class="eyebrow">{{ $organization->code }}</span><h2>{{ $organization->name }}</h2><p>{{ $organization->legalEntity?->name ?? 'Legal entity not assigned' }}</p><span class="status">{{ $organization->is_active ? 'Active' : 'Inactive' }}</span><p>Explore people, teams and reporting lines →</p></a>
@empty
<div class="panel"><h2>No organizations available</h2><p>An administrator can create organizations and grant membership through Users & access.</p></div>
@endforelse
</div>
</x-layouts.app>
