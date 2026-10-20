<x-layouts.app>
<div class="mb-6"><p class="text-sm text-slate-500">Cross-functional / Module 20</p><h1 class="text-3xl font-bold">Notifications, Approvals &amp; Automation</h1><p class="mt-1 text-slate-600">Review alerts, route decisions through approval stages and manage organization notification rules.</p></div>

<section class="mb-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
    @foreach(['Unread notifications'=>$stats['unread'], 'My approval requests'=>$stats['my_pending'], 'Requests awaiting approval'=>$stats['pending_approval'], 'Failed deliveries'=>$stats['failed_delivery']] as $label=>$value)
    <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm"><p class="text-sm text-slate-500">{{ $label }}</p><p class="mt-1 text-2xl font-semibold text-slate-900">{{ $value }}</p></div>
    @endforeach
</section>

<div class="grid gap-5 xl:grid-cols-2">
<section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
    <div class="mb-4 flex items-start justify-between"><div><h2 class="text-xl font-semibold">Notification centre</h2><p class="mt-1 text-sm text-slate-500">In-app alerts for this account.</p></div><span class="rounded-full bg-emerald-50 px-3 py-1 text-sm font-medium text-emerald-800">{{ $unreadCount }} unread</span></div>
    <div class="divide-y divide-slate-100">
    @forelse($notifications as $notification)
        @php($data = $notification->data ?? [])
        <div class="flex items-start justify-between gap-4 py-3 {{ $notification->read_at ? 'opacity-70' : '' }}"><div><p class="font-medium">{{ $data['title'] ?? 'Notification' }} @unless($notification->read_at)<span class="ml-1 inline-block h-2 w-2 rounded-full bg-emerald-500"></span>@endunless</p><p class="mt-1 text-sm text-slate-600">{{ $data['body'] ?? '' }}</p><p class="mt-1 text-xs text-slate-400">{{ $notification->created_at->format('Y-m-d H:i') }}</p></div>@unless($notification->read_at)<form method="POST" action="{{ route('automation.notifications.read', $notification->id) }}">@csrf<button class="text-sm font-medium text-emerald-700 hover:underline">Mark read</button></form>@endunless</div>
    @empty <p class="py-4 text-sm text-slate-500">No notifications yet.</p> @endforelse
    </div>{{ $notifications->links() }}
</section>

<section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
    <div class="mb-4"><h2 class="text-xl font-semibold">My notification preferences</h2><p class="mt-1 text-sm text-slate-500">Choose which channels to use for each event. SMS is sent only when the user has active TXTConnect consent.</p></div>
    @foreach(['approval.requested'=>'Approval requested','approval.decided'=>'Approval decision','automation.reminder'=>'Approval reminders','system.alert'=>'System alerts'] as $event=>$label)
    @php($preference = $preferences->get($event))
    <form method="POST" action="{{ route('automation.preferences.store') }}" class="mb-3 rounded-lg bg-slate-50 p-3">@csrf<input type="hidden" name="event_key" value="{{ $event }}"><p class="mb-2 font-medium">{{ $label }}</p><div class="mb-3 flex flex-wrap gap-4 text-sm">@foreach(['in_app'=>'In-app','email'=>'Email','sms'=>'SMS'] as $field=>$channel)<label class="inline-flex items-center gap-2"><input type="hidden" name="{{ $field }}" value="0"><input type="checkbox" name="{{ $field }}" value="1" @checked($preference ? $preference->$field : $field === 'in_app')> {{ $channel }}</label>@endforeach</div><button class="text-sm font-medium text-emerald-700 hover:underline">Save preferences</button></form>
    @endforeach
</section>

<section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
    <h2 class="text-xl font-semibold">Approval inbox</h2><p class="mb-4 mt-1 text-sm text-slate-500">Only the active stage can decide. Active time-bound delegates may act for the assigned approver.</p>
    @forelse($pendingSteps as $step)
    <div class="mb-3 rounded-lg border border-slate-200 p-4"><div class="flex flex-wrap items-start justify-between gap-2"><div><p class="font-semibold">{{ $step->title }}</p><p class="mt-1 whitespace-pre-line text-sm text-slate-600">{{ $step->details }}</p><p class="mt-2 text-xs text-slate-500">Stage {{ $step->stage }} · Submitted {{ \Illuminate\Support\Carbon::parse($step->created_at)->format('Y-m-d H:i') }} · Due {{ $step->due_at ? \Illuminate\Support\Carbon::parse($step->due_at)->format('Y-m-d H:i') : '—' }}</p></div></div><form method="POST" action="{{ route('automation.steps.decide', $step->step_id) }}" class="mt-3 grid gap-2 sm:grid-cols-[1fr_auto_auto]">@csrf<textarea name="decision_note" rows="1" maxlength="2000" class="rounded-lg border-slate-300 text-sm" placeholder="Decision note (optional)"></textarea><button name="decision" value="approved" class="workspace-action workspace-action--primary">Approve</button><button name="decision" value="rejected" class="workspace-action">Reject</button></form></div>
    @empty <p class="py-3 text-sm text-slate-500">No approvals are waiting for you.</p> @endforelse
</section>

<section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
    <h2 class="text-xl font-semibold">Submit an approval request</h2><p class="mb-4 mt-1 text-sm text-slate-500">Select a configured workflow. Each stage must be approved before the next stage opens.</p>
    <form method="POST" action="{{ route('automation.requests.store') }}" class="grid gap-3">@csrf
        <label class="text-sm">Workflow<select name="workflow_id" required class="mt-1 w-full rounded-lg border-slate-300"><option value="">Choose workflow</option>@foreach($workflows->where('is_active', true) as $workflow)<option value="{{ $workflow->id }}">{{ $workflow->name }} ({{ $workflow->stages }} stage{{ $workflow->stages === 1 ? '' : 's' }})</option>@endforeach</select></label>
        <label class="text-sm">Request title<input name="title" required maxlength="200" class="mt-1 w-full rounded-lg border-slate-300"></label>
        <label class="text-sm">Details<textarea name="details" rows="3" maxlength="5000" class="mt-1 w-full rounded-lg border-slate-300"></textarea></label>
        <button class="workspace-action workspace-action--primary">Submit for approval</button>
    </form>
</section>

<section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
    <div class="mb-4"><h2 class="text-xl font-semibold">Approval workflows</h2><p class="mt-1 text-sm text-slate-500">Configure one to eight sequential stages. Every selected approver in a stage must approve.</p></div>
    @if($canManage)<form method="POST" action="{{ route('automation.workflows.store') }}" class="grid gap-3">@csrf<label class="text-sm">Workflow name<input name="name" required maxlength="160" class="mt-1 w-full rounded-lg border-slate-300"></label><label class="text-sm">Description<textarea name="description" rows="2" maxlength="2000" class="mt-1 w-full rounded-lg border-slate-300"></textarea></label>
    @for($stage=0;$stage<8;$stage++)<label class="text-sm">Stage {{ $stage + 1 }} approvers @if($stage===0)<span class="text-rose-600">required</span>@else<span class="text-slate-400">(leave blank to end workflow)</span>@endif<select name="stage_approvers[{{ $stage }}][]" multiple class="mt-1 min-h-20 w-full rounded-lg border-slate-300">@foreach($users as $user)<option value="{{ $user->id }}">{{ $user->name }} · {{ $user->email }}</option>@endforeach</select></label>@endfor
    <label class="text-sm">SLA per stage (hours)<input type="number" min="1" max="2160" value="48" name="sla_hours" required class="mt-1 w-full rounded-lg border-slate-300"></label><button class="workspace-action workspace-action--primary">Create workflow</button></form>@else<p class="text-sm text-slate-500">You need <code>automation.manage</code> to configure workflows.</p>@endif
    <div class="mt-5 divide-y divide-slate-100">@forelse($workflows as $workflow)<div class="py-3"><p class="font-medium">{{ $workflow->name }} <span class="text-sm text-slate-500">· {{ $workflow->stages }} stage{{ $workflow->stages === 1 ? '' : 's' }} · {{ $workflow->sla_hours }}h SLA</span></p><p class="text-sm text-slate-500">{{ $workflow->description }}</p></div>@empty<p class="py-3 text-sm text-slate-500">No workflows configured yet.</p>@endforelse</div>
</section>

<section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm xl:col-span-2">
    <h2 class="text-xl font-semibold">Approval request history</h2><div class="mt-3 overflow-x-auto"><table class="w-full text-left text-sm"><thead class="bg-slate-100 text-slate-600"><tr><th class="p-3">Request</th><th class="p-3">Requester</th><th class="p-3">Stage</th><th class="p-3">Status</th><th class="p-3">Due</th></tr></thead><tbody>@forelse($requests as $item)<tr class="border-t"><td class="p-3 font-medium">{{ $item->title }}<small class="block font-normal text-slate-500">{{ $item->details }}</small></td><td class="p-3">{{ \App\Models\User::find($item->requested_by)?->name ?? 'Former user' }}</td><td class="p-3">{{ $item->current_stage }}</td><td class="p-3"><span class="rounded-full bg-slate-100 px-2 py-1">{{ ucfirst($item->status) }}</span></td><td class="p-3">{{ $item->due_at ? \Illuminate\Support\Carbon::parse($item->due_at)->format('Y-m-d H:i') : '—' }}</td></tr>@empty<tr><td class="p-4 text-center text-slate-500" colspan="5">No approval requests.</td></tr>@endforelse</tbody></table></div>{{ $requests->links() }}
</section>

@if($canManage)
<section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
    <h2 class="text-xl font-semibold">Message templates</h2><p class="mb-4 mt-1 text-sm text-slate-500">Use placeholders such as <code>{user_name}</code>, <code>{event_title}</code>, and <code>{approval_url}</code>.</p>
    <form method="POST" action="{{ route('automation.templates.store') }}" class="grid gap-3">@csrf<label class="text-sm">Name<input name="name" required maxlength="160" class="mt-1 w-full rounded-lg border-slate-300"></label><label class="text-sm">Event key<input name="event_key" required maxlength="100" value="approval.requested" class="mt-1 w-full rounded-lg border-slate-300"></label><label class="text-sm">Channel<select name="channel" class="mt-1 w-full rounded-lg border-slate-300"><option value="in_app">In-app</option><option value="email">Email</option><option value="sms">SMS</option></select></label><label class="text-sm">Subject (email)<input name="subject" maxlength="200" class="mt-1 w-full rounded-lg border-slate-300"></label><label class="text-sm">Message body<textarea name="body" required rows="3" maxlength="5000" class="mt-1 w-full rounded-lg border-slate-300"></textarea></label><button class="workspace-action">Save template</button></form>
    <div class="mt-4 divide-y divide-slate-100">@forelse($templates as $template)<p class="py-2 text-sm"><strong>{{ $template->name }}</strong> · {{ $template->event_key }} · {{ strtoupper($template->channel) }}<small class="block text-slate-500">{{ \Illuminate\Support\Str::limit($template->body, 140) }}</small></p>@empty<p class="py-2 text-sm text-slate-500">No templates yet.</p>@endforelse</div>
</section>
<section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
    <h2 class="text-xl font-semibold">Automation rules</h2><p class="mb-4 mt-1 text-sm text-slate-500">Rules use a small, safe event and action set. Overdue approval reminders are evaluated by the scheduler.</p>
    <form method="POST" action="{{ route('automation.rules.store') }}" class="grid gap-3">@csrf<label class="text-sm">Rule name<input name="name" required maxlength="160" class="mt-1 w-full rounded-lg border-slate-300"></label><label class="text-sm">Event<select name="event_key" class="mt-1 w-full rounded-lg border-slate-300"><option value="approval.overdue">Approval overdue</option></select></label><div class="grid gap-3 sm:grid-cols-3"><label class="text-sm">Condition field<select name="condition_field" class="mt-1 w-full rounded-lg border-slate-300"><option value="">Always</option><option value="status">Request status</option><option value="current_stage">Current stage</option><option value="title">Title</option></select></label><label class="text-sm">Operator<select name="condition_operator" class="mt-1 w-full rounded-lg border-slate-300"><option value="equals">Equals</option><option value="not_equals">Does not equal</option><option value="contains">Contains</option></select></label><label class="text-sm">Value<input name="condition_value" maxlength="200" class="mt-1 w-full rounded-lg border-slate-300"></label></div><label class="text-sm">Action<select name="action" class="mt-1 w-full rounded-lg border-slate-300"><option value="notify_requester">Notify requester</option><option value="notify_approver">Notify current approver</option></select></label><button class="workspace-action">Save rule</button></form>
    <div class="mt-4 divide-y divide-slate-100">@forelse($rules as $rule)<p class="py-2 text-sm"><strong>{{ $rule->name }}</strong> · {{ $rule->event_key }} · {{ str_replace('_',' ', $rule->action) }}</p>@empty<p class="py-2 text-sm text-slate-500">No rules configured.</p>@endforelse</div>
</section>
@endif

<section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm xl:col-span-2">
    <h2 class="text-xl font-semibold">Delivery log</h2><p class="mb-3 mt-1 text-sm text-slate-500">Records in-app deliveries and channel outcomes.</p><div class="overflow-x-auto"><table class="w-full text-left text-sm"><thead class="bg-slate-100 text-slate-600"><tr><th class="p-3">Time</th><th class="p-3">Event</th><th class="p-3">Channel</th><th class="p-3">Recipient</th><th class="p-3">Status</th><th class="p-3">Failure</th></tr></thead><tbody>@forelse($deliveryLogs as $log)<tr class="border-t"><td class="p-3">{{ \Illuminate\Support\Carbon::parse($log->created_at)->format('Y-m-d H:i') }}</td><td class="p-3">{{ $log->event_key }}</td><td class="p-3">{{ strtoupper($log->channel) }}</td><td class="p-3">{{ $log->recipient }}</td><td class="p-3">{{ ucfirst($log->status) }}</td><td class="p-3">{{ $log->failure_reason }}</td></tr>@empty<tr><td class="p-4 text-center text-slate-500" colspan="6">No delivery attempts recorded.</td></tr>@endforelse</tbody></table></div>{{ $deliveryLogs->links() }}
</section>
</div>
</x-layouts.app>
