<x-layouts.app>
@include('components.admin.navigation', ['section'=>'transfer'])
<section class="panel"><h2>Import reference data</h2><p class="help">CSV or XLSX, first worksheet, up to 1,000 rows / 2 MB. Import supports countries, regions, industries and customer categories. Existing category/code pairs are updated. User accounts and credentials cannot be imported or exported here.</p>
<form class="grid-form" method="POST" action="{{ route('admin.transfer.upload') }}" enctype="multipart/form-data">
@csrf<label class="wide">Data file<input type="file" name="file" accept=".csv,.xlsx" required></label><div class="wide"><button class="button">Upload and map columns</button></div></form></section>
@if($upload)
<section class="panel"><h2>Map columns</h2><p class="help">{{ count($upload['rows']) }} data rows uploaded. Map each required field, then validate the complete file.</p>
<form class="grid-form" method="POST" action="{{ route('admin.transfer.preview') }}">
@csrf
@foreach(['category','code','label','parent_code','is_active'] as $field)
<label>{{ str_replace('_',' ',ucfirst($field)) }}<select name="mapping[{{ $field }}]" 
@if(in_array($field,['category','code','label'])) required 
@endif><option value="">{{ $field==='is_active' ? 'Default: active (1)' : 'Not mapped' }}</option>
@foreach($upload['header'] as $index=>$header)<option value="{{ $index }}" 
@selected($header===$field)>{{ $header }}</option>
@endforeach</select></label>
@endforeach
<p class="help">Category values: country, region, industry, customer_category. Active values: 1 or 0. Regions require a parent country code.</p><div class="wide"><button class="button">Validate and preview</button></div></form></section>
@endif
@if($preview)
<section class="panel"><h2>Ready to import {{ count($preview) }} rows</h2><p class="help">All rows passed validation. Showing the first 20. Saving will create or update the mapped reference values.</p><div class="table-scroll"><table class="admin-table"><thead><tr><th>Category</th><th>Code</th><th>Label</th><th>Parent</th><th>Active</th></tr></thead><tbody>
@foreach(array_slice($preview,0,20) as $row)<tr>
@foreach($row as $value)<td>{{ $value }}</td>
@endforeach</tr>
@endforeach</tbody></table></div>
<form class="row-actions" method="POST" action="{{ route('admin.transfer.commit') }}">
@csrf<button class="button">Save validated import</button></form></section>
@endif
<section class="panel"><h2>Export reference data</h2><p class="help">Download the catalogue, or open the branded print template and use your browser’s Save as PDF option.</p><form class="row-actions" method="POST" action="{{ route('admin.transfer.export') }}">
@csrf<label>Format<select class="admin-input" name="format"><option value="csv">CSV</option><option value="xlsx">Excel (.xlsx)</option><option value="print">Branded print / PDF</option></select></label><button class="button secondary">Export catalogue</button></form></section>
</x-layouts.app>
