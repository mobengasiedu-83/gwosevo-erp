@php
$navigation = app(\App\Support\WorkspaceNavigation::class)->groups(auth()->user());
$currentLabel = request()->routeIs('user-manual') ? 'User manual' : 'Workspace';
$matchesNavigationLink = fn ($link) => request()->routeIs($link['match']) && collect($link['parameters'] ?? [])->every(fn ($value, $key) => (string) (request()->route($key) ?? request()->query($key)) === (string) $value);
foreach ($navigation as $links) {
    foreach ($links as $link) {
        if ($matchesNavigationLink($link)) { $currentLabel = $link['label']; }
    }
}
$switchableOrganizations = auth()->user()->hasRole('Administrator') ? \App\Models\Organization::where('is_active',true)->get() : auth()->user()->organizations()->where('organizations.is_active',true)->get();
$unreadNotificationCount = auth()->user()->unreadNotifications()->count();
@endphp
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ $currentLabel }} · {{ $erpSettings->get('company_name') }}</title>
<link rel="icon" type="image/webp" href="{{ asset('images/brand/favicon.webp') }}">
@vite(['resources/css/app.css','resources/js/app.js'])
<link rel="stylesheet" href="{{ asset('css/brand-fonts.css') }}"><link rel="stylesheet" href="{{ asset('css/administration.css') }}?v={{ filemtime(public_path('css/administration.css')) }}">
<link rel="stylesheet" href="{{ asset('css/workspace-components.css') }}?v={{ filemtime(public_path('css/workspace-components.css')) }}">
<link rel="stylesheet" href="{{ asset('css/form-system.css') }}?v={{ filemtime(public_path('css/form-system.css')) }}">
<style>:root{--erp-brand:{{ $erpSettings->get('brand_color') }}}</style>
@livewireStyles
</head>
<body class="workspace-body">
<a href="#main-content" class="skip-link">Skip to main content</a>
<button class="sidebar-scrim" data-sidebar-close aria-label="Close navigation" tabindex="-1"></button>
<aside class="workspace-sidebar" id="workspace-sidebar" aria-label="Workspace navigation">
<div class="sidebar-brand"><x-ui.brand :light="true" /><button class="icon-button sidebar-close" data-sidebar-close aria-label="Close navigation"><x-ui.icon name="close" /></button></div>
<div class="workspace-chip"><span class="workspace-chip-mark">G</span><div><strong>{{ auth()->user()->activeOrganization?->name ?: $erpSettings->get('company_name') }}</strong><small>Staff workspace</small></div><span class="connection-dot" title="Signed in"></span></div>
<nav class="sidebar-navigation" data-nav-user="{{ auth()->id() }}">
@foreach($navigation as $group => $links)
<details class="nav-group" @if(collect($links)->contains($matchesNavigationLink)) open @endif data-nav-group="{{ $group }}"><summary class="nav-group-label">{{ $group }}<x-ui.icon name="chevron" /></summary><div class="nav-group-links">
@foreach($links as $link)
<a class="sidebar-link is-submenu-link {{ $matchesNavigationLink($link) ? 'is-active' : '' }}" href="{{ route($link['route'], $link['parameters'] ?? []) }}"
@if($matchesNavigationLink($link)) aria-current="page"
@endif title="{{ $link['label'] }}" data-navigation-label="{{ $link['label'] }}"><x-ui.icon :name="$link['icon']" /><span>{{ $link['label'] }}</span>
@if(request()->routeIs($link['match']))<span class="nav-active-dot"></span>
@endif</a>
@endforeach
</div></details>
@endforeach
</nav>
<a class="sidebar-link sidebar-manual-link {{ request()->routeIs('user-manual') ? 'is-active' : '' }}" href="{{ route('user-manual') }}" @if(request()->routeIs('user-manual')) aria-current="page" @endif title="User manual" data-navigation-label="User manual"><x-ui.icon name="file" /><span>User manual</span></a>
<div class="sidebar-note"><x-ui.icon name="leaf" /><p>Connected teams.<br><strong>Stronger outcomes.</strong></p><span class="brand-stripes"><i></i><i></i><i></i></span></div>
<form method="POST" action="{{ route('logout') }}" class="sidebar-signout">
@csrf<button aria-label="Sign out"><x-ui.icon name="logout" /><span>Sign out</span></button></form>
</aside>
<div class="workspace-frame">
<header class="workspace-topbar">
<div class="topbar-location"><button class="icon-button sidebar-collapse" data-sidebar-toggle aria-label="Collapse sidebar" aria-controls="workspace-sidebar" aria-expanded="true"><x-ui.icon name="menu" /></button><button class="icon-button mobile-menu" data-sidebar-open aria-label="Open navigation" aria-controls="workspace-sidebar" aria-expanded="false"><x-ui.icon name="menu" /></button><span class="breadcrumb-home">Workspace</span><x-ui.icon name="chevron" /><strong>{{ $currentLabel }}</strong></div>
<div class="topbar-actions"><button class="quick-search" data-command-open><x-ui.icon name="search" /><span>Quick navigation</span><kbd>⌘ K</kbd></button><a class="icon-button notification-bell" href="{{ route('workspace.notifications.index') }}" aria-label="Notifications" data-notification-bell data-feed-url="{{ route('workspace.notifications.feed') }}" data-unread="{{ $unreadNotificationCount }}"><x-ui.icon name="bell" /><span data-notification-count @if(!$unreadNotificationCount) hidden @endif>{{ $unreadNotificationCount }}</span></a><a class="icon-button" href="{{ route('identity.reviews.index') }}" aria-label="My access reviews"><x-ui.icon name="file" /></a><details class="account-menu"><summary><span class="user-avatar">{{ mb_strtoupper(mb_substr(auth()->user()->name,0,1)) }}</span><span class="account-summary"><strong>{{ auth()->user()->name }}</strong><small>{{ auth()->user()->roles->first()?->name ?: 'Staff member' }}</small></span><x-ui.icon name="chevron" /></summary><div class="account-popover"><p>{{ auth()->user()->email }}</p><a href="{{ route('workspace.profile.edit') }}"><x-ui.icon name="users" />My profile</a><a href="{{ route('security.index') }}"><x-ui.icon name="shield" />My security</a><form method="POST" action="{{ route('logout') }}">
@csrf<button aria-label="Sign out"><x-ui.icon name="logout" />Sign out</button></form></div></details></div>
</header>
<div class="workspace-notification-toast" data-notification-toast role="status" aria-live="polite" hidden><a data-notification-toast-link href="{{ route('workspace.notifications.index') }}"><strong data-notification-toast-title></strong><span data-notification-toast-body></span></a><button type="button" data-notification-toast-close aria-label="Dismiss notification">×</button></div>
<main id="main-content" class="workspace-content" tabindex="-1">
@if($switchableOrganizations->count()>1)
<form class="organization-switch no-print" method="POST" action="{{ route('identity.organization') }}">
@csrf<label for="workspace-organization">Active organization</label><select id="workspace-organization" name="organization_id">
@foreach($switchableOrganizations as $organization)<option value="{{ $organization->id }}" @selected(auth()->user()->active_organization_id===$organization->id)>{{ $organization->name }}</option>
@endforeach</select><button class="button secondary">Switch</button></form>
@endif
@if($erpSettings->get('maintenance_enabled'))<div class="notice" role="status"><x-ui.icon name="clock" />Maintenance is enabled. Administrators retain workspace access.</div>
@endif
@if($erpSettings->get('operational_notice'))<div class="notice" role="status">{{ $erpSettings->get('operational_notice') }}</div>
@endif
@if(session('success'))<div class="notice success" role="status"><x-ui.icon name="check" /><span>{{ session('success') }}</span><button class="icon-button" data-dismiss-notice aria-label="Dismiss notification"><x-ui.icon name="close" /></button></div>
@endif
@if($errors->any())<div class="notice error" role="alert"><div><strong>Please check the following</strong><ul>
@foreach($errors->all() as $error)<li>{{ $error }}</li>
@endforeach</ul></div></div>
@endif
{!! $slot ?? $__env->yieldContent('content') !!}
</main>
<footer class="workspace-footer"><span>© {{ now()->year }} GWO SEVO Solutions</span><span><i></i> One connected workspace</span></footer>
</div>
<dialog class="command-dialog" id="workspace-command"><div class="command-input"><x-ui.icon name="search" /><input type="search" data-command-search aria-label="Search workspace pages" placeholder="Where would you like to go?" autocomplete="off"><button class="icon-button" data-command-close aria-label="Close quick navigation"><x-ui.icon name="close" /></button></div><div class="command-results">
@foreach($navigation as $group => $links)
@foreach($links as $link)<a href="{{ route($link['route'], $link['parameters'] ?? []) }}" data-command-item><x-ui.icon :name="$link['icon']" /><span>{{ $link['label'] }}<small>{{ $group }}</small></span><x-ui.icon name="arrow" /></a>
@endforeach 
@endforeach<p class="command-empty" hidden>No matching pages. Try a different name.</p></div><div class="command-footer">Quick navigation <span>Esc to close</span></div></dialog>
<dialog class="confirm-dialog" id="workspace-confirm" aria-labelledby="confirm-title"><span class="confirm-symbol"><x-ui.icon name="shield" /></span><h2 id="confirm-title">Confirm this change</h2><p data-confirm-message></p><div class="row-actions"><button class="button secondary" data-confirm-cancel>Cancel</button><button class="button" data-confirm-accept>Confirm change</button></div></dialog>
<script>
(() => {
    const nav = document.querySelector('.sidebar-navigation');
    if (!nav) return;
    const key = `gwo.sidebar.scroll.{{ auth()->id() }}`;
    nav.querySelectorAll('a.sidebar-link').forEach((link) => link.addEventListener('click', () => {
        try { sessionStorage.setItem(key, String(nav.scrollTop)); } catch {}
    }));
    requestAnimationFrame(() => {
        try {
            const saved = sessionStorage.getItem(key);
            if (saved !== null) nav.scrollTop = Number(saved) || 0;
        } catch {}
    });
})();
</script>
{{ app(\App\Domain\Administration\LivewireAssets::class)->scripts() }}
</body></html>
