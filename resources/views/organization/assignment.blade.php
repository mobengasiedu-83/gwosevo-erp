<x-layouts.app title="Organization assignment">
<div class="page-heading"><div><span class="eyebrow">{{ $organization->name }}</span><h1>{{ $member->user->name }}</h1><p>Set operational assignments and reporting lines. Department and team changes revoke existing sessions so access scopes take effect immediately.</p></div></div>
@include('organization.nav')
<form method="POST" action="{{ route('structure.assign',[$organization,$member]) }}" class="panel">
@csrf
@method('PUT')
<div class="grid-form">
@foreach(['department_id'=>'Department','team_id'=>'Team','branch_id'=>'Branch','position_id'=>'Position'] as $key=>$label)
<div class="field"><label for="{{ $key }}">{{ $label }}</label><select id="{{ $key }}" name="{{ $key }}"><option value="">Not assigned</option>
@foreach($choices[$key] as $item)
<option value="{{ $item->id }}" @selected((string)old($key,$member->{$key})===(string)$item->id)>{{ $item->code }} · {{ $item->title ?? $item->name }}</option>
@endforeach
</select></div>
@endforeach
@foreach(['manager_id'=>'Reporting manager','approval_manager_id'=>'Approval manager (optional override)'] as $key=>$label)
<div class="field"><label for="{{ $key }}">{{ $label }}</label><select id="{{ $key }}" name="{{ $key }}"><option value="">{{ $key==='manager_id' ? 'No manager' : 'Use reporting manager' }}</option>
@foreach($managers as $manager)
<option value="{{ $manager->user_id }}" @selected((string)old($key,$member->{$key})===(string)$manager->user_id)>{{ $manager->user->name }}</option>
@endforeach
</select></div>
@endforeach
</div><h2>Cost centre allocations</h2><p>Leave all rows empty for no allocation. Otherwise percentages must total exactly 100%.</p>
@php
$allocations=old('allocations',$member->allocations->map(fn($a)=>['cost_centre_id'=>$a->cost_centre_id,'percentage'=>$a->percentage])->all());
@endphp
@for($i=0;$i<max(3,count($allocations)+1);$i++)
<div class="grid-form"><div class="field"><label for="centre-{{ $i }}">Cost centre {{ $i+1 }}</label><select name="allocations[{{ $i }}][cost_centre_id]" id="centre-{{ $i }}"><option value="">Not allocated</option>
@foreach($choices['cost_centre_id'] as $centre)
<option value="{{ $centre->id }}" @selected((string)($allocations[$i]['cost_centre_id']??'')===(string)$centre->id)>{{ $centre->code }} · {{ $centre->name }}</option>
@endforeach
</select></div><div class="field"><label for="percentage-{{ $i }}">Percentage {{ $i+1 }}</label><input type="number" min="0.01" max="100" step="0.01" id="percentage-{{ $i }}" name="allocations[{{ $i }}][percentage]" value="{{ $allocations[$i]['percentage']??'' }}"></div></div>
@endfor
<button class="button">Save assignments</button> <a class="button secondary" href="{{ route('structure.overview',$organization) }}">Cancel</a>
</form>
</x-layouts.app>
