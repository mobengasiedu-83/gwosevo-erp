@props(['light' => false])
<a class="workspace-brand {{ $light ? 'is-light' : '' }}" href="{{ auth()->check() ? route('dashboard') : route('login') }}" aria-label="{{ $erpSettings->get('company_name') }} home">
<img src="{{ $erpSettings->get('logo_path') ? route('branding.logo') : asset($light ? 'images/brand/logo-light.webp' : 'images/brand/logo.webp') }}" alt="{{ $erpSettings->get('company_name') }}" width="166" height="40">
<span>ERP WORKSPACE</span>
</a>
