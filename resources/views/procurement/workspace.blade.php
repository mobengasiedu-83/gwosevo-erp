@extends('layouts.app')

@section('content')
<div class="page-heading">
    <div>
        <p class="eyebrow">Procurement &amp; Assets / Top-level Module 8</p>
        <h1>{{ $page['title'] }}</h1>
        <p class="muted">{{ $page['description'] }}</p>
    </div>
    <a class="button" href="{{ route('procurement.index') }}">Procurement Dashboard</a>
</div>

@if($planned)
    <section class="card">
        <h2>Workflow setup required</h2>
        <p class="muted">This item is in the approved navigation, but the functional register marks its validation, approval and finance hand-off rules for business confirmation. No records can be created here yet.</p>
    </section>
@else
    <section class="card">
        @if($section === 'approvals')
            <p class="muted">Approvers can record decisions from the Requisitions section. The requester cannot approve their own request.</p>
            <a class="button" href="{{ route('procurement.workspace', ['section' => 'requisitions']) }}">Open requisitions</a>
        @elseif(in_array($section, ['purchase-orders', 'goods-receipt'], true))
            <p class="muted">Issue purchase orders and record partial or complete receipts from the dashboard. Stocked receipt lines update inventory balances.</p>
        @elseif(in_array($section, ['asset-register', 'assignments', 'maintenance', 'transfers-disposal'], true))
            <p class="muted">Asset custody, maintenance and disposal actions are available on the dashboard's asset lifecycle panel.</p>
        @elseif($section === 'suppliers')
            <p class="muted">Supplier registration and due diligence updates are available on the dashboard.</p>
        @elseif($section === 'requisitions')
            <p class="muted">Submit purchase requests from the dashboard. Approval is a separate permission and the requester cannot approve their own request.</p>
        @elseif($section === 'inventory')
            <p class="muted">Create stock items and locations, record adjustments or transfers, and receive stocked purchase order lines from the dashboard.</p>
        @endif
        <div class="table-wrap">
            <table>
                <thead><tr>@foreach($page['columns'] as $label)<th>{{ $label }}</th>@endforeach</tr></thead>
                <tbody>
                    @forelse($page['rows'] as $row)
                        <tr>@foreach($page['columns'] as $field => $label)<td>{{ data_get($row, $field) ?? '—' }}</td>@endforeach</tr>
                    @empty
                        <tr><td class="empty-state" colspan="{{ max(1, count($page['columns'])) }}">No records to show.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endif
@endsection
