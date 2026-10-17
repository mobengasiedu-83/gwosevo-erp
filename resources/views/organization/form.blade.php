<x-layouts.app :title="($record->exists ? 'Edit ' : 'Add ').strtolower($singular)">
@php
$base=app(\App\Http\Controllers\Organization\StructureController::class)->catalogUrl($type,$organization);
@endphp
<div class="page-heading"><div><span class="eyebrow">{{ $organization?->name ?? 'Company structure' }}</span><h1>{{ $record->exists ? 'Edit' : 'Add' }} {{ strtolower($singular) }}</h1><p>Codes are permanent. Deactivate records after reassigning their active dependencies.</p></div><a class="button secondary" href="{{ $base }}">Back to {{ strtolower($title) }}</a></div>
@include('organization.nav')
<form class="panel grid-form" method="POST" action="{{ $base }}{{ $record->exists ? '/'.$record->id : '' }}">
@csrf
@if($record->exists)
@method('PUT')
@endif
@foreach($fields as $key=>[$label,$kind])
@php
$value=old($key,$record->{$key} ?? match($key){'is_active'=>1,'currency'=>'GHS','timezone'=>'Africa/Accra','kind'=>'both',default=>''});
@endphp
<div class="field"><label for="{{ $key }}">{{ $label }}</label>
@if($kind==='textarea')
<textarea id="{{ $key }}" name="{{ $key }}" rows="4">{{ $value }}</textarea>
@elseif(in_array($kind,['select','boolean','kind']))
<select id="{{ $key }}" name="{{ $key }}">
@if($kind==='select')
<option value="">Not assigned</option>
@foreach($choices[$key]??[] as $id=>$name)
<option value="{{ $id }}" @selected((string)$value===(string)$id)>{{ $name }}</option>
@endforeach
@elseif($kind==='boolean')
<option value="1" @selected((string)$value==='1')>Active</option><option value="0" @selected((string)$value==='0')>Inactive</option>
@else
@foreach(['cost'=>'Cost','revenue'=>'Revenue','both'=>'Cost and revenue'] as $id=>$name)
<option value="{{ $id }}" @selected($value===$id)>{{ $name }}</option>
@endforeach
@endif
</select>
@else
<input id="{{ $key }}" name="{{ $key }}" type="{{ $kind==='timezone' ? 'text' : $kind }}" value="{{ $value }}" @required(in_array($key,['name','title','code','timezone','currency'])) @readonly($key==='code' && $record->exists) @if($kind==='number') min="0" step="0.01" @endif>
@endif
@error($key)<span class="error">{{ $message }}</span>@enderror
</div>
@endforeach
<div><button class="button">Save {{ strtolower($singular) }}</button></div>
</form>
</x-layouts.app>
