<x-layouts.app>
<div class="overview-heading"><div><p class="eyebrow">Your workspace, at a glance</p><h1>Hello, {{ explode(' ',trim(auth()->user()->name))[0] }}<span> 👋</span></h1><p>Here’s what’s happening across your workspace.</p></div><div class="date-chip"><x-ui.icon name="calendar" />{{ now()->timezone($erpSettings->get('timezone'))->format('D, d M Y') }}</div></div>
<section class="overview-hero"><div class="hero-orbit" aria-hidden="true"></div><div class="overview-hero-copy"><span class="hero-kicker"><i></i>Connected for better outcomes</span><h2>Your people. Your progress.<br>One connected workspace.</h2><p>Bring your team, customer relationships and daily operations together.</p><div class="hero-actions">
@if($showCRM)
@can('create',App\Models\Lead::class)<a class="button" href="{{ route('leads.create') }}"><x-ui.icon name="plus" />Capture a lead</a>
@endcan
<a class="button secondary" href="{{ route('leads.index') }}">Explore CRM<x-ui.icon name="arrow" /></a>
@else
@if($showSales)<a class="button" href="{{ route('opportunities.index') }}">Open sales pipeline<x-ui.icon name="arrow" /></a>@else
<a class="button" href="{{ route('security.index') }}">My security<x-ui.icon name="arrow" /></a>
@endif
@endif
</div></div><div class="overview-hero-art"><img src="{{ asset('images/brand/workspace.webp') }}" alt="" fetchpriority="high"></div></section>
<details class="panel workspace-dashboard-settings"><summary>Customize dashboard</summary><form method="POST" action="{{ route('workspace.dashboard.preferences') }}" class="inline-actions">@csrf @method('PUT')<label><input type="checkbox" name="widgets[]" value="tasks" @checked(in_array('tasks',$workspaceWidgets,true))> Tasks</label><label><input type="checkbox" name="widgets[]" value="approvals" @checked(in_array('approvals',$workspaceWidgets,true))> Approvals</label><label><input type="checkbox" name="widgets[]" value="calendar" @checked(in_array('calendar',$workspaceWidgets,true))> Calendar</label><label><input type="checkbox" name="widgets[]" value="notifications" @checked(in_array('notifications',$workspaceWidgets,true))> Notifications</label><button class="button secondary">Save widgets</button></form></details>
<section class="metrics-grid workspace-home-metrics" aria-label="Your work summary">
@if(in_array('tasks',$workspaceWidgets,true))<a class="metric-card" href="{{ route('workspace.tasks.index') }}"><div class="metric-top"><span>My open tasks</span><span class="metric-icon"><x-ui.icon name="activity" /></span></div><p class="metric-value">{{ number_format($myTaskCount) }}</p><p class="metric-caption">{{ $myOverdueTaskCount }} overdue</p></a>@endif
@if(in_array('approvals',$workspaceWidgets,true))<a class="metric-card" href="{{ route('workspace.approvals.index') }}"><div class="metric-top"><span>My approvals</span><span class="metric-icon yellow"><x-ui.icon name="check" /></span></div><p class="metric-value">{{ number_format($myApprovalCount) }}</p><p class="metric-caption">Waiting for your decision</p></a>@endif
@if(in_array('calendar',$workspaceWidgets,true))<a class="metric-card" href="{{ route('workspace.calendar.index') }}"><div class="metric-top"><span>Today’s events</span><span class="metric-icon red"><x-ui.icon name="calendar" /></span></div><p class="metric-value">{{ number_format($myEventCount) }}</p><p class="metric-caption">On your calendar today</p></a>@endif
@if(in_array('notifications',$workspaceWidgets,true))<a class="metric-card" href="{{ route('workspace.notifications.index') }}"><div class="metric-top"><span>Unread notifications</span><span class="metric-icon gray"><x-ui.icon name="bell" /></span></div><p class="metric-value">{{ number_format($myUnreadCount) }}</p><p class="metric-caption">Personal alerts</p></a>@endif
</section>
<div class="metrics-grid">
@if($showCRM)
<div class="metric-card"><div class="metric-top"><span>Total leads</span><span class="metric-icon"><x-ui.icon name="users" /></span></div><p class="metric-value">{{ number_format($leadCount) }}</p><p class="metric-caption">Across your permitted data scope</p></div>
<div class="metric-card"><div class="metric-top"><span>Qualified leads</span><span class="metric-icon yellow"><x-ui.icon name="check" /></span></div><p class="metric-value">{{ number_format($qualifiedCount) }}</p><p class="metric-caption"><strong>{{ $leadCount ? round($qualifiedCount/$leadCount*100) : 0 }}%</strong> of your lead portfolio</p></div>
<div class="metric-card"><div class="metric-top"><span>Converted leads</span><span class="metric-icon red"><x-ui.icon name="chart" /></span></div><p class="metric-value">{{ number_format($convertedCount) }}</p><p class="metric-caption">Relationships moving forward</p></div>
<div class="metric-card"><div class="metric-top"><span>Follow-ups due</span><span class="metric-icon gray"><x-ui.icon name="clock" /></span></div><p class="metric-value">{{ number_format($followUpCount) }}</p><p class="metric-caption">Open leads needing attention</p></div>
@else
<div class="metric-card"><div class="metric-top"><span>Open access reviews</span><span class="metric-icon"><x-ui.icon name="file" /></span></div><p class="metric-value">{{ $reviewCount }}</p><p class="metric-caption">Reviews available to your account</p></div>
<div class="metric-card"><div class="metric-top"><span>Account protection</span><span class="metric-icon yellow"><x-ui.icon name="shield" /></span></div><p class="metric-value" style="font-size:21px">{{ auth()->user()->mfa_confirmed_at ? 'MFA enabled' : 'Password' }}</p><p class="metric-caption"><a href="{{ route('security.index') }}">Manage your security →</a></p></div>
@if($activeUserCount!==null)<div class="metric-card"><div class="metric-top"><span>Active staff</span><span class="metric-icon red"><x-ui.icon name="users" /></span></div><p class="metric-value">{{ $activeUserCount }}</p><p class="metric-caption">Active workspace accounts</p></div>
@endif
<div class="metric-card"><div class="metric-top"><span>Active organization</span><span class="metric-icon gray"><x-ui.icon name="grid" /></span></div><p class="metric-value" style="font-size:18px">{{ auth()->user()->activeOrganization?->code ?: 'Not assigned' }}</p><p class="metric-caption">Your current workspace context</p></div>
@endif
</div>
<div class="overview-columns"><div>
@if($showCRM)
<section class="panel overview-panel"><div class="section-heading"><div><h2>Recent leads</h2><p>Your latest customer conversations</p></div><a class="text-link" href="{{ route('leads.index') }}">View all leads<x-ui.icon name="arrow" /></a></div>
@if($recentLeads->isNotEmpty())
<div class="table-scroll"><table class="admin-table"><thead><tr><th>Contact</th><th>Company</th><th>Status</th><th>Owner</th></tr></thead><tbody>
@foreach($recentLeads as $lead)<tr><td><a class="lead-person" href="{{ route('leads.show',$lead) }}"><span class="lead-avatar">{{ mb_strtoupper(mb_substr($lead->first_name,0,1).mb_substr($lead->last_name,0,1)) }}</span><span><strong>{{ $lead->full_name }}</strong><small>{{ $lead->reference }}</small></span></a></td><td>{{ $lead->company_name ?: '—' }}</td><td><span class="status {{ in_array($lead->status,['converted','qualified']) ? 'good' : ($lead->status==='disqualified' ? 'danger' : 'warn') }}">{{ ucfirst($lead->status) }}</span></td><td>{{ $lead->owner?->name ?: 'Unassigned' }}</td></tr>
@endforeach</tbody></table></div>
@else
<div class="empty-state"><span class="empty-icon"><x-ui.icon name="users" /></span><h3>Your next relationship starts here</h3><p>Capture your first lead to start building a clear picture of your customer pipeline.</p>
@can('create',App\Models\Lead::class)<a class="text-link" href="{{ route('leads.create') }}">Capture your first lead<x-ui.icon name="arrow" /></a>
@endcan</div>
@endif
</section>
@if($followUpAlerts->isNotEmpty())<section class="panel overview-panel"><div class="section-heading"><div><h2>Follow-up alerts</h2><p>Reminders and overdue escalations</p></div></div>@foreach($followUpAlerts as $alert)<form method="POST" action="{{ route('lead-notifications.read',$alert) }}" class="timeline-item">@csrf<div><strong>{{ $alert->data['title'] ?? 'Lead follow-up' }}</strong><small>{{ $alert->data['lead_reference'] ?? '' }} · {{ $alert->data['lead_name'] ?? '' }} · {{ $alert->data['activity'] ?? '' }}</small></div><button class="text-link">Open</button></form>@endforeach</section>@endif
@if($followUpActivities->isNotEmpty())<section class="panel overview-panel"><div class="section-heading"><div><h2>Follow-ups due</h2><p>Tasks in your permitted lead scope</p></div><a class="text-link" href="{{ route('leads.index') }}">View leads</a></div>@foreach($followUpActivities as $activity)<div class="timeline-item"><span class="timeline-dot"><x-ui.icon name="clock" /></span><div><a href="{{ route('leads.show',$activity->lead) }}"><strong>{{ $activity->subject }}</strong></a><small>{{ $activity->lead->reference }} · {{ $activity->lead->full_name }}</small></div><time>{{ $erpSettings->formatDate($activity->due_at,true) }}</time></div>@endforeach</section>@endif
@endif
@if($showSales)<section class="panel overview-panel"><div class="section-heading"><div><h2>Sales next actions</h2><p>Due tasks within your opportunity access</p></div><a class="text-link" href="{{ route('opportunities.index') }}">Open pipeline<x-ui.icon name="arrow" /></a></div>@foreach($salesAlerts as $alert)<form method="POST" action="{{ route('sales-notifications.read',$alert) }}" class="timeline-item">@csrf<div><strong>{{ $alert->data['title'] ?? 'Opportunity task due' }}</strong><small>{{ $alert->data['reference'] ?? '' }} · {{ $alert->data['name'] ?? '' }} · {{ $alert->data['activity'] ?? '' }}</small></div><button class="text-link">Open</button></form>@endforeach @forelse($salesActivities as $activity)<div class="timeline-item"><span class="timeline-dot"><x-ui.icon name="clock" /></span><div><a href="{{ route('opportunities.show',$activity->opportunity) }}"><strong>{{ $activity->subject }}</strong></a><small>{{ $activity->opportunity->reference }} · {{ $activity->opportunity->name }}</small></div><time>{{ $erpSettings->formatDate($activity->due_at,true) }}</time></div>@empty @if($salesAlerts->isEmpty())<p class="text-sm text-slate-500">No opportunity actions are due.</p>@endif @endforelse</section>@endif
@if($contractAlerts->isNotEmpty())<section class="panel overview-panel"><div class="section-heading"><div><h2>Contract renewals</h2><p>Renewal dates approaching</p></div><a class="text-link" href="{{ route('commercial.contracts.index') }}">View contracts</a></div>@foreach($contractAlerts as $alert)<form method="POST" action="{{ route('commercial.renewal-notifications.read',$alert) }}" class="timeline-item">@csrf<div><strong>{{ $alert->data['reference'] ?? 'Contract' }} · {{ $alert->data['client_name'] ?? '' }}</strong><small>Renewal {{ $alert->data['renewal_date'] ?? '' }}</small></div><button class="text-link">Open</button></form>@endforeach</section>@endif
<section class="panel overview-panel"><div class="section-heading"><div><h2>Quick actions</h2><p>A shorter path to your everyday work</p></div><x-ui.icon name="grid" /></div><div class="quick-actions-grid">
@can('administer-system')
<a class="quick-action" href="{{ route('identity.users.create') }}"><x-ui.icon name="users" /><span><strong>Invite a teammate</strong><small>Grow your workspace</small></span></a><a class="quick-action" href="{{ route('admin.index') }}"><x-ui.icon name="settings" /><span><strong>Workspace settings</strong><small>Make it your own</small></span></a><a class="quick-action" href="{{ route('identity.roles.index') }}"><x-ui.icon name="shield" /><span><strong>Manage permissions</strong><small>The right access</small></span></a>
@else
<a class="quick-action" href="{{ route('security.index') }}"><x-ui.icon name="shield" /><span><strong>My security</strong><small>Manage protection</small></span></a><a class="quick-action" href="{{ route('identity.reviews.index') }}"><x-ui.icon name="file" /><span><strong>Access reviews</strong><small>Your assigned reviews</small></span></a><a class="quick-action" href="{{ route('identity.delegations.index') }}"><x-ui.icon name="arrow" /><span><strong>Delegations</strong><small>Keep work moving</small></span></a>
@endcan
</div></section>
<section class="panel overview-panel"><div class="section-heading"><div><h2>Recent activity</h2><p>{{ auth()->user()->hasRole('Administrator') ? 'Updates in your organization' : 'Your recent workspace updates' }}</p></div>
@can('administer-system')<a class="text-link" href="{{ route('identity.activity') }}">View audit<x-ui.icon name="arrow" /></a>
@endcan</div>
@forelse($recentActivity as $event)
<div class="timeline-item"><span class="timeline-dot"><x-ui.icon name="activity" /></span><div><strong>{{ ucfirst(str_replace(['identity.','admin.','lead.','.','_'],['','','',' ',' '],$event->event)) }}</strong><small>Recorded in your workspace audit trail</small></div><time datetime="{{ $event->created_at->toIso8601String() }}">{{ $erpSettings->formatDate($event->created_at,true) }}</time></div>
@empty
<div class="empty-state" style="padding:20px"><span class="empty-icon"><x-ui.icon name="activity" /></span><h3>A clear trail of progress</h3><p>Your workspace activity will appear here as work gets underway.</p></div>
@endforelse
</section></div><div>
@if($showCRM)
<section class="panel overview-panel"><div class="section-heading"><div><h2>Lead pipeline</h2><p>From first contact to conversion</p></div><x-ui.icon name="chart" /></div>
@foreach(['new'=>['New','#a9bf96'],'assigned'=>['Assigned','#dfcd80'],'qualified'=>['Qualified','#94b47c'],'converted'=>['Converted','#699c50'],'disqualified'=>['Disqualified','#d4a6aa']] as $status=>$definition)
@php($total=$statusCounts->get($status,0))
<div class="pipeline-row" style="--bar-color:{{ $definition[1] }};--bar-width:{{ $leadCount ? round($total/$leadCount*100) : 0 }}%"><div class="pipeline-label"><span><i></i>{{ $definition[0] }}</span><strong>{{ $total }}</strong></div><div class="pipeline-track" role="meter" aria-label="{{ $definition[0] }} leads" aria-valuemin="0" aria-valuemax="{{ max(1,$leadCount) }}" aria-valuenow="{{ $total }}"><div class="pipeline-fill"></div></div></div>
@endforeach
<div class="pipeline-note"><span>Conversion rate</span><strong>{{ $leadCount ? round($convertedCount/$leadCount*100) : 0 }}%</strong></div></section>
@endif
<section class="panel overview-panel"><div class="section-heading"><div><h2>Your workspace</h2><p>Foundations that keep work connected</p></div></div>
@can('administer-system')
<a class="module-summary" href="{{ route('admin.index') }}"><span class="metric-icon"><x-ui.icon name="settings" /></span><span><strong>System administration</strong><small>Settings, branding & operations</small></span><span class="status good">Installed</span></a><a class="module-summary" href="{{ route('identity.users.index') }}"><span class="metric-icon yellow"><x-ui.icon name="users" /></span><span><strong>Identity & access</strong><small>People, roles & governance</small></span><span class="status good">Installed</span></a>
@endcan
<a class="module-summary" href="{{ route('identity.reviews.index') }}"><span class="metric-icon gray"><x-ui.icon name="file" /></span><span><strong>Access reviews</strong><small>{{ $reviewCount }} open {{ $reviewCount===1 ? 'review' : 'reviews' }}</small></span><x-ui.icon name="chevron" /></a><a class="module-summary" href="{{ route('security.index') }}"><span class="metric-icon red"><x-ui.icon name="shield" /></span><span><strong>Account security</strong><small>{{ auth()->user()->mfa_confirmed_at ? 'Authenticator enabled' : 'Manage password & sessions' }}</small></span><x-ui.icon name="chevron" /></a></section>
</div></div>
</x-layouts.app>
