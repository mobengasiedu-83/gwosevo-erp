@props(['name' => 'grid'])
@php
$paths = [
'grid'=>'<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
'users'=>'<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2m20 0v-2a4 4 0 0 0-3-3.87M15 3.13a4 4 0 0 1 0 7.75"/><circle cx="9" cy="7" r="4"/>',
'settings'=>'<path d="m12 3 2 3 3-.2.8 3 2.7 1.5-1.4 2.7.6 3.1-3 .9-1.8 2.5-2.9-1-2.9 1-1.8-2.5-3-.9.6-3.1L3.5 10.3 6.2 8.8l.8-3 3 .2Z"/><circle cx="12" cy="11.8" r="3"/>',
'shield'=>'<path d="M12 22s8-4 8-11V5l-8-3-8 3v6c0 7 8 11 8 11Z"/><path d="m9 12 2 2 4-4"/>',
'check'=>'<path d="m5 12 4 4L19 6"/>',
'arrow'=>'<path d="M5 12h14m-5-5 5 5-5 5"/>',
'chevron'=>'<path d="m9 5 7 7-7 7"/>',
'search'=>'<circle cx="10.5" cy="10.5" r="7.5"/><path d="m16 16 5 5"/>',
'bell'=>'<path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9m-11 12a2 2 0 0 0 4 0"/>',
'logout'=>'<path d="M9 21H4V3h5m6 4 5 5-5 5m-7-5h12"/>',
'menu'=>'<path d="M4 6h16M4 12h16M4 18h16"/>',
'close'=>'<path d="m6 6 12 12M6 18 18 6"/>',
'plus'=>'<path d="M12 5v14M5 12h14"/>',
'chart'=>'<path d="M3 3v18h18m-15-7 5-5 4 3 6-7"/>',
'clock'=>'<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
'file'=>'<path d="M14 2H5v20h14V7ZM14 2v5h5M8 12h8m-8 4h6"/>',
'activity'=>'<path d="M2 12h5l3-9 4 18 3-9h5"/>',
'download'=>'<path d="M12 3v12m-5-5 5 5 5-5M4 17v4h16v-4"/>',
'key'=>'<circle cx="8" cy="9" r="5"/><path d="m12 12 9 9m-3-3 3-3m-6 0 3-3"/>',
'leaf'=>'<path d="M20 3C10 1 2 8 6 15s16 5 14-12ZM5 21l10-11"/>',
'calendar'=>'<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 11h18"/>',
'eye'=>'<path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/>',
];
@endphp
<svg {{ $attributes->class(['ui-icon']) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $paths[$name] ?? $paths['grid'] !!}</svg>
