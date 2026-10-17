<div class="admin-heading"><p>Administration / Module 01</p><h1>System configuration</h1><p>Manage workspace settings, master data and operational controls.</p></div>
<nav class="admin-tabs" aria-label="Administration sections">
@foreach(['general'=>'General','branding'=>'Branding','preferences'=>'Preferences','modules'=>'Modules','fields'=>'Custom fields','references'=>'Reference data','sequences'=>'Numbering','health'=>'System health','maintenance'=>'Maintenance','audit'=>'Audit history'] as $key=>$label)
<a href="{{ route('admin.index', ['section'=>$key]) }}" @if(($section ?? '') === $key) aria-current="page" @endif><x-ui.icon :name="match($key){'general','preferences'=>'settings','branding'=>'leaf','modules'=>'grid','fields','references'=>'file','sequences'=>'key','health'=>'activity','maintenance'=>'clock',default=>'shield'}" /><span>{{ $label }}</span></a>
@endforeach
<a href="{{ route('admin.transfer') }}" @if(($section ?? '') === 'transfer') aria-current="page" @endif><x-ui.icon name="download" /><span>Import / export</span></a>
<a href="{{ route('identity.users.index') }}"><x-ui.icon name="users" /><span>Users & access</span></a>
</nav>
