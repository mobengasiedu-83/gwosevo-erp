@props(['name'])
@php
[$domain, $action] = array_pad(explode('.', $name, 2), 2, '');
$action = $action === 'update' ? 'edit' : $action;
@endphp
{{ ucfirst(str_replace('_', ' ', $action)).' '.str_replace('_', ' ', $domain) }}
