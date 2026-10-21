<?php

namespace App\Http\Controllers;

use App\Models\ApprovalDelegation;
use App\Models\HrEmployee;
use App\Models\SmsConsent;
use App\Models\SmsMessage;
use App\Models\User;
use App\Jobs\SendSmsMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AutomationController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(app(\App\Domain\Administration\Modules::class)->enabled('automation'), 404);
        abort_unless($request->user()->isSuperAdmin() || $request->user()->canAny(['automation.view', 'automation.approve', 'automation.manage']), 403);
        $organizationId = $request->user()->active_organization_id;
        abort_unless($organizationId && $request->user()->canAccessOrganization((int) $organizationId), 403);
        $user = $request->user();
        $canManage = $user->isSuperAdmin() || $user->can('automation.manage');
        $visibleRequests = DB::table('automation_approval_requests as r')
            ->where('r.organization_id', $organizationId)
            ->where(function ($query) use ($user) {
                $query->where('r.requested_by', $user->id)->orWhereExists(function ($steps) use ($user) {
                    $steps->selectRaw('1')->from('automation_approval_steps as s')->whereColumn('s.request_id', 'r.id')->where('s.approver_id', $user->id);
                });
            });

        return view('automation.index', [
            'organizationId' => $organizationId,
            'canManage' => $canManage,
            'unreadCount' => $user->unreadNotifications()->count(),
            'notifications' => $user->notifications()->latest()->paginate(10, ['*'], 'notifications_page'),
            'workflows' => DB::table('automation_workflows')->where('organization_id', $organizationId)->latest()->get(),
            'requests' => $visibleRequests->select('r.*')->latest('r.created_at')->paginate(15),
            'pendingSteps' => DB::table('automation_approval_steps as s')->join('automation_approval_requests as r', 'r.id', '=', 's.request_id')->where('r.organization_id', $organizationId)->where('s.approver_id', $user->id)->whereColumn('s.stage', 'r.current_stage')->where('s.status', 'pending')->where('r.status', 'pending')->select('s.id as step_id', 's.stage', 'r.id as request_id', 'r.title', 'r.details', 'r.requested_by', 'r.due_at', 'r.created_at')->latest('r.created_at')->get(),
            'users' => User::query()->where('status', 'active')->whereHas('organizations', fn ($query) => $query->where('organizations.id', $organizationId))->orderBy('name')->get(['id', 'name', 'email']),
            'templates' => DB::table('automation_templates')->where('organization_id', $organizationId)->latest()->get(),
            'rules' => DB::table('automation_rules')->where('organization_id', $organizationId)->latest()->get(),
            'preferences' => DB::table('automation_preferences')->where('organization_id', $organizationId)->where('user_id', $user->id)->get()->keyBy('event_key'),
            'deliveryLogs' => DB::table('automation_delivery_logs')->where('organization_id', $organizationId)->when(! $canManage, fn ($query) => $query->where('user_id', $user->id))->latest()->paginate(15, ['*'], 'logs_page'),
            'stats' => [
                'unread' => $user->unreadNotifications()->count(),
                'my_pending' => (clone $visibleRequests)->where('r.status', 'pending')->count(),
                'pending_approval' => DB::table('automation_approval_requests')->where('organization_id', $organizationId)->where('status', 'pending')->count(),
                'failed_delivery' => DB::table('automation_delivery_logs')->where('organization_id', $organizationId)->where('status', 'failed')->count(),
            ],
        ]);
    }

    public function markRead(Request $request, string $notification)
    {
        $item = $request->user()->notifications()->whereKey($notification)->firstOrFail();
        $item->markAsRead();
        return back()->with('success', 'Notification marked as read.');
    }

    public function workflowStore(Request $request)
    {
        $organizationId = $this->organizationId($request, 'automation.manage');
        $request->merge(['stage_approvers' => collect($request->input('stage_approvers', []))->filter(fn ($stage) => is_array($stage) && count($stage) > 0)->values()->all()]);
        $data = $request->validate(['name' => ['required', 'string', 'max:160'], 'description' => ['nullable', 'string', 'max:2000'], 'stage_approvers' => ['required', 'array', 'min:1', 'max:8'], 'stage_approvers.*' => ['required', 'array', 'min:1'], 'stage_approvers.*.*' => ['required', 'integer', Rule::exists('users', 'id')->where('status', 'active')], 'sla_hours' => ['required', 'integer', 'min:1', 'max:2160']]);
        $stages = array_values($data['stage_approvers']);
        foreach ($stages as $stageUsers) {
            foreach (array_unique(array_map('intval', $stageUsers)) as $approverId) {
                abort_unless(User::findOrFail($approverId)->canAccessOrganization((int) $organizationId), 422, 'Every approver must belong to this organization.');
            }
        }
        DB::table('automation_workflows')->insert(['organization_id' => $organizationId, 'name' => $data['name'], 'description' => $data['description'] ?? null, 'stages' => count($stages), 'stage_approvers' => json_encode(array_map(fn ($stage) => array_values(array_unique(array_map('intval', $stage))), $stages)), 'sla_hours' => $data['sla_hours'], 'is_active' => true, 'created_by' => $request->user()->id, 'created_at' => now(), 'updated_at' => now()]);
        return back()->with('success', 'Approval workflow created.');
    }

    public function requestStore(Request $request)
    {
        $organizationId = $this->organizationId($request);
        $data = $request->validate(['workflow_id' => ['required', 'integer', Rule::exists('automation_workflows', 'id')->where('organization_id', $organizationId)->where('is_active', true)], 'title' => ['required', 'string', 'max:200'], 'details' => ['nullable', 'string', 'max:5000']]);
        $workflow = DB::table('automation_workflows')->where('id', $data['workflow_id'])->where('organization_id', $organizationId)->first();
        $approvers = json_decode($workflow->stage_approvers, true) ?: [];
        if (collect($approvers)->flatten()->contains((int) $request->user()->id)) {
            throw ValidationException::withMessages(['workflow_id' => 'You cannot request approval from a workflow that includes yourself.']);
        }
        DB::transaction(function () use ($request, $data, $workflow, $approvers, $organizationId) {
            $id = DB::table('automation_approval_requests')->insertGetId(['organization_id' => $organizationId, 'workflow_id' => $workflow->id, 'title' => $data['title'], 'details' => $data['details'] ?? null, 'requested_by' => $request->user()->id, 'current_stage' => 1, 'status' => 'pending', 'due_at' => now()->addHours($workflow->sla_hours), 'created_at' => now(), 'updated_at' => now()]);
            $this->createStageSteps($id, 1, $approvers[0] ?? []);
            $this->notifyApprovers($id, 1, $approvers[0] ?? [], $organizationId);
        });
        return back()->with('success', 'Approval request submitted to its first stage.');
    }

    public function decide(Request $request, int $step)
    {
        $data = $request->validate(['decision' => ['required', Rule::in(['approved', 'rejected'])], 'decision_note' => ['nullable', 'string', 'max:2000']]);
        $actor = $request->user();
        DB::transaction(function () use ($step, $data, $actor) {
            $approvalStep = DB::table('automation_approval_steps')->where('id', $step)->lockForUpdate()->first();
            abort_unless($approvalStep, 404);
            $approval = DB::table('automation_approval_requests')->where('id', $approvalStep->request_id)->lockForUpdate()->first();
            abort_unless($approval && $approval->organization_id === $actor->active_organization_id && $approval->status === 'pending' && $approval->current_stage === $approvalStep->stage && $approvalStep->status === 'pending', 404);
            abort_unless($approvalStep->approver_id === $actor->id || $this->isValidDelegate($approvalStep->approver_id, $actor->id, $approval->organization_id), 403);
            abort_unless((int) $approval->requested_by !== (int) $actor->id, 403, 'Requesters cannot approve their own requests.');
            DB::table('automation_approval_steps')->where('id', $step)->update(['status' => $data['decision'], 'decided_by' => $actor->id, 'decision_note' => $data['decision_note'] ?? null, 'decided_at' => now(), 'updated_at' => now()]);
            if ($data['decision'] === 'rejected') {
                DB::table('automation_approval_steps')->where('request_id', $approval->id)->where('status', 'pending')->update(['status' => 'skipped', 'updated_at' => now()]);
                DB::table('automation_approval_requests')->where('id', $approval->id)->update(['status' => 'rejected', 'completed_at' => now(), 'updated_at' => now()]);
                $this->notifyUser($approval->requested_by, $approval->organization_id, 'approval.decided', 'Approval rejected', 'Your request “'.$approval->title.'” was rejected.', 'automation.index');
                return;
            }
            $remaining = DB::table('automation_approval_steps')->where('request_id', $approval->id)->where('stage', $approvalStep->stage)->where('status', 'pending')->exists();
            if ($remaining) return;
            $workflow = DB::table('automation_workflows')->where('id', $approval->workflow_id)->first();
            $approvers = json_decode($workflow?->stage_approvers ?? '[]', true) ?: [];
            $nextStage = (int) $approvalStep->stage + 1;
            if (isset($approvers[$nextStage - 1])) {
                DB::table('automation_approval_requests')->where('id', $approval->id)->update(['current_stage' => $nextStage, 'due_at' => now()->addHours($workflow->sla_hours), 'updated_at' => now()]);
                $this->createStageSteps($approval->id, $nextStage, $approvers[$nextStage - 1]);
                $this->notifyApprovers($approval->id, $nextStage, $approvers[$nextStage - 1], $approval->organization_id);
            } else {
                DB::table('automation_approval_requests')->where('id', $approval->id)->update(['status' => 'approved', 'completed_at' => now(), 'updated_at' => now()]);
                $this->notifyUser($approval->requested_by, $approval->organization_id, 'approval.decided', 'Approval complete', 'Your request “'.$approval->title.'” was approved.', 'automation.index');
            }
        });
        return back()->with('success', 'Approval decision recorded.');
    }

    public function preferenceSave(Request $request)
    {
        $organizationId = $this->organizationId($request);
        $data = $request->validate(['event_key' => ['required', 'string', 'max:100', Rule::in(['approval.requested', 'approval.decided', 'automation.reminder', 'system.alert', 'workspace.task.assigned', 'workspace.calendar.reminder'])], 'in_app' => ['sometimes', 'boolean'], 'email' => ['sometimes', 'boolean'], 'sms' => ['sometimes', 'boolean']]);
        DB::table('automation_preferences')->updateOrInsert(['organization_id' => $organizationId, 'user_id' => $request->user()->id, 'event_key' => $data['event_key']], ['in_app' => (bool) ($data['in_app'] ?? false), 'email' => (bool) ($data['email'] ?? false), 'sms' => (bool) ($data['sms'] ?? false), 'updated_at' => now(), 'created_at' => now()]);
        return back()->with('success', 'Notification preferences saved.');
    }

    public function templateStore(Request $request)
    {
        $organizationId = $this->organizationId($request, 'automation.manage');
        $data = $request->validate(['name' => ['required', 'string', 'max:160'], 'event_key' => ['required', 'string', 'max:100'], 'channel' => ['required', Rule::in(['in_app', 'email', 'sms'])], 'subject' => ['nullable', 'string', 'max:200'], 'body' => ['required', 'string', 'max:5000']]);
        DB::table('automation_templates')->insert([...$data, 'organization_id' => $organizationId, 'is_active' => true, 'created_by' => $request->user()->id, 'created_at' => now(), 'updated_at' => now()]);
        return back()->with('success', 'Notification template saved.');
    }

    public function ruleStore(Request $request)
    {
        $organizationId = $this->organizationId($request, 'automation.manage');
        $data = $request->validate(['name' => ['required', 'string', 'max:160'], 'event_key' => ['required', 'string', 'max:100', Rule::in(['approval.overdue'])], 'condition_field' => ['nullable', Rule::in(['status', 'current_stage', 'title'])], 'condition_operator' => ['required', Rule::in(['equals', 'not_equals', 'contains'])], 'condition_value' => ['nullable', 'string', 'max:200'], 'action' => ['required', Rule::in(['notify_requester', 'notify_approver'])]]);
        DB::table('automation_rules')->insert([...$data, 'organization_id' => $organizationId, 'is_active' => true, 'created_by' => $request->user()->id, 'created_at' => now(), 'updated_at' => now()]);
        return back()->with('success', 'Automation rule saved. Rules are evaluated by the scheduled automation command.');
    }

    private function organizationId(Request $request, string $permission = 'automation.view'): int
    {
        $user = $request->user();
        abort_unless(app(\App\Domain\Administration\Modules::class)->enabled('automation'), 404);
        $hasPermission = $permission === 'automation.view'
            ? $user->canAny(['automation.view', 'automation.approve', 'automation.manage'])
            : $user->can($permission);
        abort_unless($user->isSuperAdmin() || $hasPermission, 403);
        abort_unless($user->active_organization_id && $user->canAccessOrganization((int) $user->active_organization_id), 403);
        return (int) $user->active_organization_id;
    }

    private function createStageSteps(int $requestId, int $stage, array $users): void
    {
        foreach (array_values(array_unique(array_map('intval', $users))) as $approverId) {
            DB::table('automation_approval_steps')->insert(['request_id' => $requestId, 'stage' => $stage, 'approver_id' => $approverId, 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    private function notifyApprovers(int $requestId, int $stage, array $users, int $organizationId): void
    {
        $title = DB::table('automation_approval_requests')->where('id', $requestId)->value('title');
        foreach (array_unique(array_map('intval', $users)) as $userId) {
            $this->notifyUser($userId, $organizationId, 'approval.requested', 'Approval required', 'Please review “'.$title.'” (stage '.$stage.').', 'automation.index');
        }
    }

    private function notifyUser(int $userId, int $organizationId, string $event, string $title, string $body, string $url): void
    {
        $user = User::find($userId);
        if (! $user) return;
        $preference = DB::table('automation_preferences')->where('organization_id', $organizationId)->where('user_id', $userId)->where('event_key', $event)->first();
        $channels = $preference ?: (object) ['in_app' => true, 'email' => false, 'sms' => false];
        $templateFor = fn (string $channel) => DB::table('automation_templates')->where('organization_id', $organizationId)->where('event_key', $event)->where('channel', $channel)->where('is_active', true)->latest('id')->first();
        if ($channels->in_app) {
            $template = $templateFor('in_app');
            $rendered = $template ? $this->renderTemplate($template->body, $user, $title) : $body;
            $user->notify(new \App\Notifications\AutomationNotification($title, $rendered, route($url), $event, $organizationId));
            $this->logDelivery($organizationId, $userId, $event, 'in_app', $user->email, 'sent', null, ['title' => $title]);
        }
        if ($channels->email) {
            $template = $templateFor('email');
            $subject = $template?->subject ?: $title;
            $message = $template ? $this->renderTemplate($template->body, $user, $title) : $body."\n\n".route($url);
            try {
                Mail::raw($message, fn ($mail) => $mail->to($user->email)->subject($subject));
                $this->logDelivery($organizationId, $userId, $event, 'email', $user->email, 'sent', null, ['subject' => $subject]);
            } catch (Throwable $exception) {
                $this->logDelivery($organizationId, $userId, $event, 'email', $user->email, 'failed', mb_substr($exception->getMessage(), 0, 1000));
            }
        }
        if ($channels->sms) {
            $phone = HrEmployee::query()->where('organization_id', $organizationId)->where('user_id', $userId)->value('work_phone');
            $phone = $phone ? preg_replace('/[^0-9+]/', '', $phone) : null;
            $configured = filled(config('services.txtconnect.key')) && filled(config('services.txtconnect.sender')) && str_starts_with((string) config('services.txtconnect.base_url'), 'https://');
            if (! $phone || ! $configured || ! app(\App\Domain\Administration\Modules::class)->enabled('sms') || ! SmsConsent::query()->where('organization_id', $organizationId)->where('phone', $phone)->where('status', 'opted_in')->exists()) {
                $this->logDelivery($organizationId, $userId, $event, 'sms', $phone, 'failed', 'SMS needs a work phone, active TXTConnect configuration, enabled SMS module, and current opt-in consent.');
            } else {
                $template = $templateFor('sms');
                $smsBody = $template ? $this->renderTemplate($template->body, $user, $title) : mb_substr($title.': '.$body, 0, 1600);
                try {
                    $message = SmsMessage::create(['organization_id' => $organizationId, 'to' => $phone, 'sender' => config('services.txtconnect.sender'), 'body' => $smsBody, 'status' => 'queued', 'idempotency_key' => (string) Str::uuid(), 'created_by' => null]);
                    SendSmsMessage::dispatch($message->id)->onQueue('sms');
                    $this->logDelivery($organizationId, $userId, $event, 'sms', $phone, 'queued', null, ['sms_message_id' => $message->id]);
                } catch (Throwable $exception) {
                    $this->logDelivery($organizationId, $userId, $event, 'sms', $phone, 'failed', mb_substr($exception->getMessage(), 0, 1000));
                }
            }
        }
    }

    private function renderTemplate(string $template, User $user, string $title): string
    {
        return strtr($template, ['{user_name}' => $user->name, '{event_title}' => $title, '{approval_url}' => route('automation.index')]);
    }

    private function logDelivery(int $organizationId, int $userId, string $event, string $channel, ?string $recipient, string $status, ?string $failure = null, array $metadata = []): void
    {
        DB::table('automation_delivery_logs')->insert(['organization_id' => $organizationId, 'user_id' => $userId, 'event_key' => $event, 'channel' => $channel, 'recipient' => $recipient, 'status' => $status, 'attempts' => 1, 'failure_reason' => $failure, 'metadata' => $metadata ? json_encode($metadata) : null, 'sent_at' => in_array($status, ['sent', 'queued'], true) ? now() : null, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function isValidDelegate(int $approverId, int $actorId, int $organizationId): bool
    {
        return ApprovalDelegation::query()->where('organization_id', $organizationId)->where('delegator_id', $approverId)->where('delegate_id', $actorId)->where('starts_at', '<=', now())->where('ends_at', '>=', now())->whereNull('revoked_at')->exists();
    }
}
