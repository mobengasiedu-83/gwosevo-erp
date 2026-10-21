<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\User;
use App\Notifications\AutomationNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class WorkspaceController extends Controller
{
    private function organization(Request $request): Organization
    {
        $organization = $request->user()->activeOrganization;
        abort_unless($organization && $request->user()->canAccessOrganization((int) $organization->id), 403, 'Choose an active workspace first.');

        return $organization;
    }

    private function task(Request $request, int $id): object
    {
        $task = DB::table('workspace_tasks')
            ->where('organization_id', $this->organization($request)->id)
            ->where('id', $id)
            ->where(function ($query) use ($request) {
                $query->where('assigned_to', $request->user()->id)->orWhere('created_by', $request->user()->id);
            })->first();
        abort_unless($task, 404);

        return $task;
    }

    public function tasks(Request $request): View
    {
        $organization = $this->organization($request);
        $user = $request->user();
        $status = $request->query('status');
        $tasks = DB::table('workspace_tasks as t')->join('users as assignee', 'assignee.id', '=', 't.assigned_to')
            ->leftJoin('users as creator', 'creator.id', '=', 't.created_by')
            ->where('t.organization_id', $organization->id)
            ->where(function ($query) use ($user) { $query->where('t.assigned_to', $user->id)->orWhere('t.created_by', $user->id); })
            ->when(in_array($status, ['open', 'in_progress', 'done'], true), fn ($query) => $query->where('t.status', $status))
            ->select('t.*', 'assignee.name as assignee_name', 'creator.name as creator_name')
            ->orderByRaw("CASE WHEN t.status = 'done' THEN 1 ELSE 0 END")
            ->orderByRaw('t.due_at IS NULL')->orderBy('t.due_at')->orderByDesc('t.created_at')->get();
        $tasks->each(function ($task) {
            $task->comments = DB::table('workspace_task_comments as c')->join('users as u', 'u.id', '=', 'c.user_id')
                ->where('c.task_id', $task->id)->orderBy('c.created_at')->get(['c.id', 'c.user_id', 'c.body', 'c.created_at', 'u.name'])
                ->map(function ($comment) {
                    $comment->body = Crypt::decryptString($comment->body);
                    return $comment;
                });
        });
        $users = $organization->users()->where('users.status', 'active')->orderBy('users.name')->get(['users.id', 'users.name']);

        return view('workspace.tasks', compact('organization', 'tasks', 'users', 'status'));
    }

    public function dashboardPreferencesUpdate(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'widgets' => ['nullable', 'array'],
            'widgets.*' => ['required', Rule::in(['tasks', 'approvals', 'calendar', 'notifications'])],
        ]);
        DB::table('workspace_dashboard_preferences')->updateOrInsert(
            ['user_id' => $request->user()->id],
            ['widgets' => json_encode(array_values(array_unique($data['widgets'] ?? []))), 'created_at' => now(), 'updated_at' => now()],
        );

        return to_route('dashboard')->with('success', 'Dashboard preferences saved.');
    }

    public function taskStore(Request $request): RedirectResponse
    {
        $organization = $this->organization($request);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:5000'],
            'priority' => ['required', Rule::in(['low', 'normal', 'high', 'urgent'])],
            'due_at' => ['nullable', 'date'],
            'assigned_to' => ['required', 'integer', Rule::exists('users', 'id')->where('status', 'active')],
        ]);
        abort_unless(User::findOrFail($data['assigned_to'])->canAccessOrganization((int) $organization->id), 422, 'Choose a user in this organization.');

        $id = DB::table('workspace_tasks')->insertGetId([
            ...$data, 'organization_id' => $organization->id, 'created_by' => $request->user()->id,
            'status' => 'open', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $assignmentPreference = DB::table('automation_preferences')->where('organization_id', $organization->id)
            ->where('user_id', $data['assigned_to'])->where('event_key', 'workspace.task.assigned')->first();
        if ((int) $data['assigned_to'] !== (int) $request->user()->id && ($assignmentPreference?->in_app ?? true)) {
            User::findOrFail($data['assigned_to'])->notify(new AutomationNotification(
                'A task was assigned to you', $data['title'], route('workspace.tasks.index'), 'workspace.task.assigned', (int) $organization->id,
            ));
        }

        return to_route('workspace.tasks.index')->with('success', 'Task created and assigned.');
    }

    public function taskUpdate(Request $request, int $id): RedirectResponse
    {
        $task = $this->task($request, $id);
        $data = $request->validate([
            'status' => ['required', Rule::in(['open', 'in_progress', 'done'])],
            'priority' => ['nullable', Rule::in(['low', 'normal', 'high', 'urgent'])],
            'due_at' => ['nullable', 'date'],
        ]);
        DB::table('workspace_tasks')->where('id', $task->id)->update([
            'status' => $data['status'], 'priority' => $data['priority'] ?? $task->priority,
            'due_at' => $data['due_at'] ?? null, 'completed_at' => $data['status'] === 'done' ? now() : null, 'updated_at' => now(),
        ]);

        return back()->with('success', 'Task updated.');
    }

    public function taskComment(Request $request, int $id): RedirectResponse
    {
        $task = $this->task($request, $id);
        $data = $request->validate(['body' => ['required', 'string', 'max:4000']]);
        DB::table('workspace_task_comments')->insert([
            'task_id' => $task->id, 'user_id' => $request->user()->id,
            'body' => Crypt::encryptString($data['body']), 'created_at' => now(), 'updated_at' => now(),
        ]);

        return back()->with('success', 'Comment added to the task.');
    }

    public function approvals(Request $request): View
    {
        $organization = $this->organization($request);
        $userId = $request->user()->id;
        $pendingSteps = DB::table('automation_approval_steps as s')->join('automation_approval_requests as r', 'r.id', '=', 's.request_id')
            ->leftJoin('users as requester', 'requester.id', '=', 'r.requested_by')
            ->where('r.organization_id', $organization->id)->where('s.approver_id', $userId)
            ->whereColumn('s.stage', 'r.current_stage')->where('s.status', 'pending')->where('r.status', 'pending')
            ->orderByRaw('r.due_at IS NULL')->orderBy('r.due_at')->select('s.id as step_id', 's.stage', 'r.id as request_id', 'r.title', 'r.details', 'r.due_at', 'r.created_at', 'requester.name as requester_name')->get();
        $history = DB::table('automation_approval_requests as r')->join('automation_approval_steps as s', 's.request_id', '=', 'r.id')
            ->leftJoin('users as requester', 'requester.id', '=', 'r.requested_by')
            ->where('r.organization_id', $organization->id)->where(function ($query) use ($userId) {
                $query->where('r.requested_by', $userId)->orWhere('s.approver_id', $userId);
            })->where(function ($query) use ($userId) {
                $query->whereIn('r.status', ['approved', 'rejected'])->orWhere(fn ($pending) => $pending->where('s.approver_id', $userId)->where('s.status', '!=', 'pending'));
            })->select('r.id', 'r.title', 'r.status', 'r.completed_at', 'r.created_at', 'requester.name as requester_name', 's.status as step_status', 's.decision_note')
            ->orderByDesc('r.completed_at')->limit(50)->get();

        return view('workspace.approvals', compact('pendingSteps', 'history'));
    }

    public function calendar(Request $request): View
    {
        $organization = $this->organization($request);
        $month = $request->validate(['month' => ['nullable', 'date_format:Y-m']])['month'] ?? now()->format('Y-m');
        $start = \Illuminate\Support\Carbon::createFromFormat('Y-m-d', $month.'-01')->startOfMonth();
        $end = $start->copy()->endOfMonth();
        $userId = (int) $request->user()->id;
        $visibleStart = $start->copy()->startOfWeek();
        $visibleEnd = $end->copy()->endOfWeek();
        $events = DB::table('workspace_calendar_events')->where('organization_id', $organization->id)
            ->where('user_id', $userId)->whereBetween('starts_at', [$visibleStart, $visibleEnd])->get()
            ->map(function ($event) {
                $event->kind = 'personal';
                return $event;
            });
        $taskDeadlines = DB::table('workspace_tasks')->where('organization_id', $organization->id)
            ->where('assigned_to', $userId)->where('status', '!=', 'done')->whereNotNull('due_at')
            ->whereBetween('due_at', [$visibleStart, $visibleEnd])->get()->map(fn ($task) => (object) [
                'id' => $task->id, 'kind' => 'task', 'title' => $task->title,
                'starts_at' => $task->due_at, 'all_day' => false, 'reminder_at' => null,
            ]);
        $approvalDeadlines = DB::table('automation_approval_steps as s')->join('automation_approval_requests as r', 'r.id', '=', 's.request_id')
            ->where('r.organization_id', $organization->id)->where('s.approver_id', $userId)
            ->whereColumn('s.stage', 'r.current_stage')->where('s.status', 'pending')->where('r.status', 'pending')
            ->whereNotNull('r.due_at')->whereBetween('r.due_at', [$visibleStart, $visibleEnd])->get(['r.id', 'r.title', 'r.due_at'])
            ->map(fn ($approval) => (object) [
                'id' => $approval->id, 'kind' => 'approval', 'title' => $approval->title,
                'starts_at' => $approval->due_at, 'all_day' => false, 'reminder_at' => null,
            ]);
        $events = $events->concat($taskDeadlines)->concat($approvalDeadlines)
            ->sortBy('starts_at')->values();

        return view('workspace.calendar', compact('events', 'month', 'start', 'end'));
    }

    public function calendarStore(Request $request): RedirectResponse
    {
        $organization = $this->organization($request);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:180'], 'description' => ['nullable', 'string', 'max:4000'],
            'starts_at' => ['required', 'date'], 'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'reminder_at' => ['nullable', 'date', 'before:starts_at'], 'all_day' => ['nullable', 'boolean'],
        ]);
        DB::table('workspace_calendar_events')->insert([
            ...$data, 'organization_id' => $organization->id, 'user_id' => $request->user()->id,
            'all_day' => (bool) ($data['all_day'] ?? false), 'created_at' => now(), 'updated_at' => now(),
        ]);

        return to_route('workspace.calendar.index', ['month' => substr($data['starts_at'], 0, 7)])->with('success', 'Calendar event saved.');
    }

    public function calendarDestroy(Request $request, int $id): RedirectResponse
    {
        $this->organization($request);
        DB::table('workspace_calendar_events')->where('organization_id', $request->user()->active_organization_id)
            ->where('user_id', $request->user()->id)->where('id', $id)->delete();

        return back()->with('success', 'Calendar event removed.');
    }

    public function notifications(Request $request): View
    {
        $organization = $this->organization($request);
        $unreadOnly = $request->boolean('unread');
        $notifications = $request->user()->notifications()->when($unreadOnly, fn ($query) => $query->whereNull('read_at'))
            ->latest()->paginate(20)->withQueryString();
        $preferences = DB::table('automation_preferences')->where('organization_id', $organization->id)
            ->where('user_id', $request->user()->id)->get()->keyBy('event_key');
        $events = [
            'approval.requested' => 'Approval requests', 'approval.decided' => 'Approval decisions',
            'automation.reminder' => 'Workflow reminders', 'system.alert' => 'System alerts',
            'workspace.task.assigned' => 'Task assignments (in-app)',
            'workspace.calendar.reminder' => 'Calendar reminders (in-app)',
        ];

        return view('workspace.notifications', compact('notifications', 'preferences', 'events', 'unreadOnly'));
    }

    public function notificationFeed(Request $request): JsonResponse
    {
        $this->organization($request);
        $user = $request->user();
        $latest = $user->notifications()->latest()->limit(5)->get()->map(fn ($item) => [
            'id' => $item->id, 'title' => $item->data['title'] ?? 'Notification',
            'body' => $item->data['body'] ?? '', 'url' => $item->data['url'] ?? route('workspace.notifications.index'),
            'created_at' => $item->created_at?->toIso8601String(), 'unread' => $item->read_at === null,
        ]);

        return response()->json(['unread' => $user->unreadNotifications()->count(), 'items' => $latest]);
    }

    public function notificationRead(Request $request, string $id): RedirectResponse
    {
        $notification = $request->user()->notifications()->whereKey($id)->firstOrFail();
        $notification->markAsRead();

        return back()->with('success', 'Notification marked as read.');
    }

    public function notificationArchive(Request $request, string $id): RedirectResponse
    {
        $request->user()->notifications()->whereKey($id)->delete();

        return back()->with('success', 'Notification archived.');
    }

    public function notificationPreferences(Request $request): RedirectResponse
    {
        $organization = $this->organization($request);
        $data = $request->validate([
            'event_key' => ['required', Rule::in(['approval.requested', 'approval.decided', 'automation.reminder', 'system.alert', 'workspace.task.assigned', 'workspace.calendar.reminder'])],
            'in_app' => ['nullable', 'boolean'], 'email' => ['nullable', 'boolean'], 'sms' => ['nullable', 'boolean'],
        ]);
        DB::table('automation_preferences')->updateOrInsert(
            ['organization_id' => $organization->id, 'user_id' => $request->user()->id, 'event_key' => $data['event_key']],
            ['in_app' => (bool) ($data['in_app'] ?? false), 'email' => (bool) ($data['email'] ?? false), 'sms' => (bool) ($data['sms'] ?? false), 'created_at' => now(), 'updated_at' => now()],
        );

        return back()->with('success', 'Notification preferences saved.');
    }

    public function profile(Request $request): View
    {
        return view('workspace.profile');
    }

    public function profileUpdate(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50', 'regex:/^[0-9+().\-\s]*$/'],
        ]);
        $request->user()->forceFill($data)->save();

        return to_route('workspace.profile.edit')->with('success', 'Profile updated.');
    }
}
