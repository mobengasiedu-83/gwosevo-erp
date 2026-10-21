@props(['title' => 'Welcome back', 'description' => '', 'eyebrow' => 'Your connected workspace'])
<!doctype html><html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ $title }} · {{ $erpSettings->get('company_name') }}</title><link rel="icon" type="image/webp" href="{{ asset('images/brand/favicon.webp') }}">
@vite(['resources/css/app.css','resources/js/app.js'])
<link rel="stylesheet" href="{{ asset('css/brand-fonts.css') }}"><link rel="stylesheet" href="{{ asset('css/administration.css') }}?v={{ filemtime(public_path('css/administration.css')) }}"><link rel="stylesheet" href="{{ asset('css/form-system.css') }}?v={{ filemtime(public_path('css/form-system.css')) }}"><style>:root{--erp-brand:{{ $erpSettings->get('brand_color') }}}</style>
</head><body class="auth-body"><div class="auth-layout">
<aside class="auth-visual" aria-label="GWO SEVO workspace"><x-ui.brand :light="true" /><img class="auth-image" src="{{ asset('images/brand/workspace.webp') }}" alt="" fetchpriority="high"><div class="auth-message"><p class="auth-eyebrow">People. Possibilities. Progress.</p><h2>Great work starts<br>with <em>connection.</em></h2><p>One workspace for the people, customers and ideas moving GWO SEVO forward.</p></div><div class="auth-visual-footer"><span>Built around your team's potential.</span><span class="auth-dots" aria-hidden="true"><i></i><i></i><i></i></span></div></aside>
<main class="auth-main"><div class="auth-topline"><x-ui.icon name="shield" /><span>GWO SEVO staff workspace</span></div><div class="auth-content"><x-ui.brand /><p class="auth-form-eyebrow">{{ $eyebrow }}</p><h1>{{ $title }}</h1><p class="auth-description">{{ $description }}</p>
@if($errors->any())<div class="notice error" role="alert">{{ $errors->first() }}</div>
@endif
@if(session('status'))<div class="notice success" role="status">{{ session('status') }}</div>
@endif
{{ $slot }}
<x-turnstile />
</div><footer class="auth-footer"><span>© {{ now()->year }} GWO SEVO Solutions. All rights reserved.</span><a href="https://gwosevo.com" target="_blank" rel="noopener noreferrer">gwosevo.com ↗</a></footer></main>
</div></body></html>
