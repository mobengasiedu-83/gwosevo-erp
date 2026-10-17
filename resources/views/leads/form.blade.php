<x-layouts.app><h1 class="mb-6 text-3xl font-bold">{{ $lead->exists ? 'Edit lead' : 'Capture lead' }}</h1><form method="POST" action="{{ $lead->exists ? route('leads.update',$lead) : route('leads.store') }}" class="grid gap-4 rounded bg-white p-6 shadow-sm md:grid-cols-2">
@csrf 
@if($lead->exists)
@method('PUT')
@endif 
@foreach(['first_name'=>'First name','last_name'=>'Last name','email'=>'Email','phone'=>'Phone','company_name'=>'Company','industry'=>'Industry'] as $field=>$label)<label class="text-sm font-medium">{{ $label }}<input class="mt-1 w-full rounded border-slate-300" name="{{ $field }}" value="{{ old($field,$lead->$field) }}" 
@if(in_array($field,['first_name','last_name'])) required 
@endif></label>
@endforeach<label class="text-sm font-medium">Source<select class="mt-1 w-full rounded border-slate-300" name="source">
@foreach(['manual','website','api','referral','inbound_call','event'] as $source)<option value="{{ $source }}" 
@selected(old('source',$lead->source ?: 'manual')===$source)>{{ ucfirst(str_replace('_',' ',$source)) }}</option>
@endforeach</select></label><label class="text-sm font-medium">Next follow-up<input class="mt-1 w-full rounded border-slate-300" type="datetime-local" name="next_follow_up_at" value="{{ old('next_follow_up_at',optional($lead->next_follow_up_at)->format('Y-m-d\TH:i')) }}"></label><label class="text-sm font-medium md:col-span-2">Interests / notes<textarea class="mt-1 w-full rounded border-slate-300" name="interests">{{ old('interests',$lead->interests) }}</textarea></label>
@foreach($customFields as $field)
<label class="text-sm font-medium">{{ $field->label }} 
@if($field->is_required)<span aria-label="required">*</span>
@endif
@if($field->type==='select')<select class="admin-input" name="custom_values[{{ $field->key }}]" 
@required($field->is_required)><option value="">Select an option</option>
@foreach($field->options ?? [] as $option)<option 
@selected(old('custom_values.'.$field->key, $lead->custom_values[$field->key] ?? null)===$option)>{{ $option }}</option>
@endforeach</select>
@else<input class="admin-input" name="custom_values[{{ $field->key }}]" type="{{ $field->type==='number' ? 'number' : ($field->type==='date' ? 'date' : 'text') }}" 
@if($field->type==='number') step="any" 
@endif value="{{ old('custom_values.'.$field->key,$lead->custom_values[$field->key] ?? '') }}" 
@required($field->is_required)>
@endif</label>
@endforeach
<div class="md:col-span-2"><button class="rounded bg-indigo-600 px-4 py-2 font-semibold text-white">Save lead</button><a class="ml-3" href="{{ $lead->exists ? route('leads.show',$lead) : route('leads.index') }}">Cancel</a></div></form></x-layouts.app>