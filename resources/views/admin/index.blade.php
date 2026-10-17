<x-layouts.app>
@include('components.admin.navigation')
@if(in_array($section, ['general','branding','preferences','maintenance']))
<section class="panel">
<h2>{{ ucfirst($section) }} settings</h2>
<form method="POST" action="{{ route('admin.settings') }}" enctype="multipart/form-data" class="grid-form">
@csrf 
@method('PUT')<input type="hidden" name="section" value="{{ $section }}">
@if($section === 'general')
@foreach(['company_name'=>'Company name','company_email'=>'Contact email','company_phone'=>'Contact phone','company_address'=>'Address'] as $key=>$label)
<label>{{ $label }}<input name="{{ $key }}" value="{{ old($key, $erpSettings->get($key)) }}" 
@if($key==='company_name') required 
@endif 
@if($key==='company_email') type="email" 
@endif maxlength="{{ $key==='company_address' ? 1000 : 150 }}"></label>
@endforeach
<label>Time zone<select name="timezone">
@foreach(DateTimeZone::listIdentifiers() as $timezone)<option 
@selected(old('timezone',$erpSettings->get('timezone'))===$timezone)>{{ $timezone }}</option>
@endforeach</select></label>
<label>Date format<select name="date_format">
@foreach(['d/m/Y','m/d/Y','Y-m-d','d M Y'] as $format)<option 
@selected(old('date_format',$erpSettings->get('date_format'))===$format)>{{ $format }}</option>
@endforeach</select></label>
@elseif($section === 'branding')
<label>Login message<input name="login_message" value="{{ old('login_message',$erpSettings->get('login_message')) }}" required maxlength="300"></label>
<label>Accent colour<input type="color" name="brand_color" value="{{ old('brand_color',$erpSettings->get('brand_color')) }}" required></label>
<label>Company logo<input type="file" name="logo" accept="image/png,image/jpeg,image/webp"><span class="help">PNG, JPEG or WebP; maximum 2 MB and 2400 × 2400 pixels.</span></label>
<label>Logo action<select name="remove_logo"><option value="0">Keep current logo</option value="1">Remove current logo</option></select></label>
@if($erpSettings->get('logo_path'))<div class="wide"><img class="erp-logo" src="{{ route('branding.logo') }}" alt="Current company logo"></div>
@endif
<label class="wide">Document footer<textarea name="document_footer" maxlength="500">{{ old('document_footer',$erpSettings->get('document_footer')) }}</textarea><span class="help">Used by the branded print / PDF template in Import / export.</span></label>
@elseif($section === 'preferences')
<label>Currency code<input name="currency" value="{{ old('currency',$erpSettings->get('currency')) }}" pattern="[A-Z]{3}" maxlength="3" required><span class="help">Three-letter ISO code, for example GHS.</span></label>
<label>Financial year starts<select name="financial_year_month">
@foreach(range(1,12) as $month)<option value="{{ $month }}" 
@selected((int)old('financial_year_month',$erpSettings->get('financial_year_month'))===$month)>{{ DateTime::createFromFormat('!m', (string)$month)->format('F') }}</option>
@endforeach</select></label>
<label>Records per page<select name="pagination">
@foreach([10,15,25,50,100] as $size)<option 
@selected((int)old('pagination',$erpSettings->get('pagination'))===$size)>{{ $size }}</option>
@endforeach</select></label>
@elseif($section === 'maintenance')
<div class="notice wide">Administrators retain access during maintenance. Staff see a service notice. The sign-in page remains available so administrators can restore service.</div>
<label>Workspace availability<select name="maintenance_enabled"><option value="0" 
@selected(!old('maintenance_enabled',$erpSettings->get('maintenance_enabled')))>Available</option><option value="1" 
@selected(old('maintenance_enabled',$erpSettings->get('maintenance_enabled')))>Maintenance</option></select></label>
<label>Operational notice<textarea name="operational_notice" maxlength="500">{{ old('operational_notice',$erpSettings->get('operational_notice')) }}</textarea><span class="help">Shown to signed-in staff. Leave blank to hide.</span></label>
<label class="wide">Maintenance message<textarea name="maintenance_message" required maxlength="500">{{ old('maintenance_message',$erpSettings->get('maintenance_message')) }}</textarea></label>
@endif
<div class="wide"><button class="button">Save settings</button></div>
</form></section>
@elseif($section === 'modules')
<section class="panel"><h2>Module availability</h2><p class="help">Core authentication and organization services stay enabled. Future modules can be enabled only after their features are installed.</p>
<div class="table-scroll"><table class="admin-table"><thead><tr><th>Module</th><th>State</th><th>Control</th></tr></thead><tbody>
@foreach(config('erp.modules') as $key=>$module)
<tr><td>{{ $module['name'] }}
@if($key==='organization')<p class="help">Installed: legal entities, organizations, departments, branches, teams, positions, reporting lines, cost centres and organization charts.</p>
@endif</td><td><span class="status {{ $modules->enabled($key) ? 'good' : '' }}">{{ !$module['available'] ? 'Not installed' : ($modules->enabled($key) ? 'Enabled' : 'Disabled') }}</span></td><td>
@if($module['available'] && !($module['required'] ?? false))<form method="POST" action="{{ route('admin.module',$key) }}">
@csrf 
@method('PUT')<input type="hidden" name="enabled" value="{{ $modules->enabled($key) ? 0 : 1 }}"><button class="button secondary">{{ $modules->enabled($key) ? 'Disable' : 'Enable' }}</button></form>
@elseif($module['required'] ?? false)Core dependency
@endif
</td></tr>
@endforeach
</tbody></table></div></section>
@elseif($section === 'fields')
<section class="panel"><h2>Add a lead field</h2><p class="help">Supported record: CRM lead. Keys and types are fixed after creation to preserve existing data.</p>
<form method="POST" action="{{ route('admin.fields.store') }}" class="grid-form">
@csrf
<label>Field key<input name="key" pattern="[a-z][a-z0-9_]{0,49}" value="{{ old('key') }}" required placeholder="service_interest"></label>
<label>Label<input name="label" value="{{ old('label') }}" required maxlength="100"></label>
<label>Type<select name="type">
@foreach(['text','number','date','select'] as $type)<option 
@selected(old('type')===$type)>{{ $type }}</option>
@endforeach</select></label>
<label>Required<select name="is_required"><option value="0">Optional</option><option value="1">Required</option></select></label>
<label class="wide">Select options, one per line<textarea name="options_text">{{ old('options_text') }}</textarea></label>
<input type="hidden" name="is_active" value="1"><div class="wide"><button class="button">Add custom field</button></div>
</form></section>
@forelse($fields as $field)
<section class="panel"><h2>{{ $field->label }} <span class="status {{ $field->is_active ? 'good' : '' }}">{{ $field->is_active ? 'Active' : 'Disabled' }}</span></h2><p class="help">{{ $field->key }} · {{ $field->type }} · Lead</p>
<details class="record-edit"><summary>Edit field</summary><form class="grid-form" method="POST" action="{{ route('admin.fields.update',$field) }}">
@csrf 
@method('PUT')
<label>Label<input name="label" value="{{ $field->label }}" required maxlength="100"></label>
<label>Required<select name="is_required"><option value="0" 
@selected(!$field->is_required)>Optional</option><option value="1" 
@selected($field->is_required)>Required</option></select></label>
<label>Availability<select name="is_active"><option value="1" 
@selected($field->is_active)>Active</option><option value="0" 
@selected(!$field->is_active)>Disabled</option></select></label>
@if($field->type==='select')<label>Select options<textarea name="options_text">{{ implode("\n",$field->options ?? []) }}</textarea></label>
@endif
<div class="wide"><button class="button">Save field</button></div></form></details></section>
@empty<p class="help">No custom fields configured.</p>
@endforelse
@elseif($section === 'references')
<section class="panel"><h2>Add reference value</h2><form method="POST" action="{{ route('admin.references.store') }}" class="grid-form">
@csrf
<label>Category<select name="category">
@foreach(App\Models\ReferenceValue::CATEGORIES as $category)<option>{{ $category }}</option>
@endforeach</select></label>
<label>Code<input name="code" value="{{ old('code') }}" pattern="[A-Z0-9_-]+" maxlength="40" required placeholder="GH"></label>
<label>Label<input name="label" value="{{ old('label') }}" maxlength="150" required placeholder="Ghana"></label>
<label>Parent country code (regions only)<input name="parent_code" value="{{ old('parent_code') }}" maxlength="40" placeholder="GH"></label>
<input type="hidden" name="is_active" value="1"><div class="wide"><button class="button">Add reference value</button></div></form></section>
<section class="panel"><h2>Reference catalogue</h2><div class="table-scroll"><table class="admin-table"><thead><tr><th>Category / code</th><th>Value</th><th>Status</th><th>Edit</th></tr></thead><tbody>
@forelse($references as $reference)<tr><td>{{ $reference->category }} / {{ $reference->code }}</td><td>{{ $reference->label }}
@if($reference->parent_code)<p class="help">Country: {{ $reference->parent_code }}</p>
@endif</td><td><span class="status {{ $reference->is_active ? 'good' : '' }}">{{ $reference->is_active ? 'Active' : 'Disabled' }}</span></td><td>
<details class="record-edit"><summary>Edit value</summary><form method="POST" action="{{ route('admin.references.update',$reference) }}" class="grid-form">
@csrf 
@method('PUT')
<input type="hidden" name="category" value="{{ $reference->category }}"><input type="hidden" name="code" value="{{ $reference->code }}">
<label>Label<input name="label" value="{{ $reference->label }}" required maxlength="150"></label>
<label>Active<select name="is_active"><option value="1" 
@selected($reference->is_active)>Active</option><option value="0" 
@selected(!$reference->is_active)>Disabled</option></select></label>
@if($reference->category==='region')<label>Country code<input name="parent_code" value="{{ $reference->parent_code }}" required></label>
@endif
<div class="wide"><button class="button">Save value</button></div></form></details></td></tr>
@empty<tr><td colspan="4">No reference values yet. Add values or import your approved master data.</td></tr>
@endforelse
</tbody></table></div><div class="row-actions">{{ $references->links() }}</div></section>
@elseif($section === 'sequences')
<div class="notice">Lead and customer sequences are connected to CRM. Ticket, quotation and invoice sequences are reserved for their future modules. Existing references remain unchanged.</div>
@foreach($sequences as $sequence)<section class="panel"><h2>{{ ucfirst($sequence->key) }} references</h2><form method="POST" action="{{ route('admin.sequence',$sequence->key) }}" class="grid-form">
@csrf 
@method('PUT')
<label>Prefix<input name="prefix" value="{{ $sequence->prefix }}" pattern="[A-Z0-9_-]{1,20}" required></label>
<label>Minimum digits<input name="padding" type="number" min="3" max="12" value="{{ $sequence->padding }}" required></label>
<label>Next number<input name="next_number" type="number" min="{{ $sequence->next_number }}" max="999999999999" value="{{ $sequence->next_number }}" required></label>
<div><p class="help">Preview: {{ $sequence->prefix.str_pad((string)$sequence->next_number,$sequence->padding,'0',STR_PAD_LEFT) }}</p></div>
<div class="wide"><button class="button">Save sequence</button></div></form></section>
@endforeach
@elseif($section === 'health')
<livewire:admin.health-panel />
@elseif($section === 'audit')
<section class="panel"><h2>Configuration audit history</h2><div class="table-scroll"><table class="admin-table"><thead><tr><th>When / actor</th><th>Action</th><th>Changes</th></tr></thead><tbody>
@forelse($events as $event)<tr><td>{{ $erpSettings->formatDate($event->created_at,true) }}<p class="help">User #{{ $event->actor_id }}</p></td><td>{{ $event->event }}</td><td><details><summary>View before and after</summary><pre class="audit-json">Before: {{ json_encode($event->old_values, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) }}
After: {{ json_encode($event->new_values, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) }}</pre></details></td></tr>
@empty<tr><td colspan="3">No administration changes recorded yet.</td></tr>
@endforelse
</tbody></table></div><div class="row-actions">{{ $events->links() }}</div></section>
@endif
</x-layouts.app>
