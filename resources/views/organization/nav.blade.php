<nav class="tabs" aria-label="Organization navigation">
<a href="{{ route('structure.index') }}" @if(request()->routeIs('structure.index')) aria-current="page" @endif><x-ui.icon name="grid" /><span>All organizations</span></a>
@if(isset($organization) && $organization)
<a href="{{ route('structure.overview',$organization) }}" @if(request()->routeIs('structure.overview','structure.assignment')) aria-current="page" @endif><x-ui.icon name="users" /><span>People & chart</span></a>
@if(auth()->user()->hasRole('Administrator'))
@foreach(['departments'=>'Departments','branches'=>'Branches','teams'=>'Teams','positions'=>'Positions','cost-centres'=>'Cost centres'] as $slug=>$label)
<a href="{{ route('structure.units.index',[$organization,$slug]) }}" @if(request()->route('type')===$slug) aria-current="page" @endif><x-ui.icon :name="match($slug){'teams','departments'=>'users','positions'=>'shield','cost-centres'=>'chart',default=>'grid'}" /><span>{{ $label }}</span></a>
@endforeach
@endif
@endif
@if(auth()->user()->hasRole('Administrator'))
<a href="{{ route('structure.catalog.index','legal-entities') }}" @if(request()->route('type')==='legal-entities') aria-current="page" @endif><x-ui.icon name="file" /><span>Legal entities</span></a><a href="{{ route('structure.catalog.index','organizations') }}" @if(request()->route('type')==='organizations') aria-current="page" @endif><x-ui.icon name="settings" /><span>Manage organizations</span></a>
@endif
</nav>
