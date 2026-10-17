<ul class="org-tree">
@foreach($children[$parent]??[] as $node)
@if(!in_array($node->user_id,$visited))
<li><details open><summary><strong>{{ $node->user->name }}</strong><span>{{ $node->position?->title ?? 'Position not assigned' }}</span><small>{{ $node->department?->name ?? 'No department' }}{{ $node->team ? ' · '.$node->team->name : '' }}</small></summary>
@include('organization.tree',['parent'=>$node->user_id,'visited'=>[...$visited,$node->user_id]])
</details></li>
@endif
@endforeach
</ul>
