<x-layouts.app :title="$title">
@php
$base=app(\App\Http\Controllers\Organization\StructureController::class)->catalogUrl($type,$organization);
@endphp
<div class="page-heading"><div><span class="eyebrow">{{ $organization?->name ?? 'Company structure' }}</span><h1>{{ $title }}</h1><p>Maintain the structure without deleting historical records.</p></div><a class="button" href="{{ $base }}/create">Add {{ strtolower($singular) }}</a></div>
@include('organization.nav')
<section class="panel"><form method="GET" class="toolbar"><label for="search">Search code or name</label><input id="search" name="search" value="{{ request('search') }}"><button class="button secondary">Search</button></form>
<div class="table-scroll"><table class="admin-table"><thead><tr><th>Code</th><th>Name / title</th><th>Status</th><th>Action</th></tr></thead><tbody>
@forelse($records as $item)
<tr><td>{{ $item->code }}</td><td>{{ $item->title ?? $item->name }}</td><td><span class="status">{{ $item->is_active ? 'Active' : 'Inactive' }}</span></td><td><a href="{{ $base }}/{{ $item->id }}/edit">Edit {{ $item->code }}</a></td></tr>
@empty
<tr><td colspan="4">No records yet. Add your first {{ strtolower($singular) }}.</td></tr>
@endforelse
</tbody></table></div>{{ $records->links() }}</section>
</x-layouts.app>
