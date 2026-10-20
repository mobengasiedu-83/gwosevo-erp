<x-layouts.app>
<div class="page-heading">
    <div><p class="eyebrow">Workspace overview</p><h1>{{ $title }}</h1><p class="muted">{{ $description }}</p></div>
</div>

@if(count($cards))
<section class="stats-grid" aria-label="{{ $title }} summary">
    @foreach($cards as $card)
    <a class="card overview-stat-card" href="{{ route($card['route']) }}">
        <span class="muted">{{ $card['label'] }}</span>
        <strong>{{ number_format($card['value']) }}</strong>
        <small class="muted">{{ $card['description'] }}</small>
    </a>
    @endforeach
</section>
@endif

<section class="card overview-module-panel">
    <div class="section-heading"><div><h2>Modules</h2><p class="muted">Open a module to view records and take action.</p></div></div>
    <div class="overview-module-grid">
        @foreach($links as $link)
        <a class="module-summary" href="{{ route($link['route'], $link['parameters'] ?? []) }}">
            <span class="metric-icon"><x-ui.icon :name="$link['icon']" /></span>
            <span><strong>{{ $link['label'] }}</strong><small>Open {{ $link['label'] }}</small></span>
            <x-ui.icon name="chevron" />
        </a>
        @endforeach
    </div>
</section>
</x-layouts.app>
