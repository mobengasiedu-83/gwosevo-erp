<?php

namespace App\Http\Controllers;

use App\Domain\Administration\Modules;
use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class SecurityCentreController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizeSecurity($request, 'security.view');
        $org = (int) $request->user()->active_organization_id;
        $admin = $request->user()->isSuperAdmin();
        $failedJobs = DB::table('failed_jobs')->count();
        $queuedJobs = DB::table('jobs')->count();
        $queueBeat = $this->settingTimestamp('queue_last_seen');
        $scheduleBeat = $this->settingTimestamp('scheduler_last_seen');

        return view('security-centre.index', [
            'organizationId' => $org,
            'isAdmin' => $admin,
            'canManage' => $admin || $request->user()->can('security.manage'),
            'canManageIncidents' => $admin || $request->user()->can('security.incidents.manage'),
            'canManageBackups' => $admin || $request->user()->can('security.backups.manage'),
            'canManageRetention' => $admin || $request->user()->can('security.retention.manage'),
            'health' => $this->health($failedJobs, $queuedJobs, $queueBeat, $scheduleBeat),
            'incidents' => DB::table('security_incidents as i')->leftJoin('users as u', 'u.id', '=', 'i.assigned_to')->where('i.organization_id', $org)->select('i.*', 'u.name as assignee')->latest('i.created_at')->paginate(10, ['*'], 'incidents')->withQueryString(),
            'backups' => DB::table('security_backup_records as b')->leftJoin('users as u', 'u.id', '=', 'b.recorded_by')->where('b.organization_id', $org)->select('b.*', 'u.name as recorded_by_name')->latest('b.backup_at')->paginate(10, ['*'], 'backups')->withQueryString(),
            'rotations' => DB::table('security_rotation_records as r')->leftJoin('users as u', 'u.id', '=', 'r.owner_id')->where('r.organization_id', $org)->select('r.*', 'u.name as owner_name')->orderByRaw('r.next_due_at is null, r.next_due_at asc')->limit(30)->get(),
            'recoveryPlan' => DB::table('security_recovery_plans')->where('organization_id', $org)->first(),
            'classifications' => DB::table('security_classifications')->orderBy('dataset')->get(),
            'retentionPolicies' => DB::table('security_retention_policies')->whereNull('organization_id')->orderBy('category')->get(),
            'auditEvents' => AuditEvent::query()->when(! $admin, fn ($q) => $q->where('organization_id', $org))->latest('id')->paginate(15, ['*'], 'audit')->withQueryString(),
            'loginEvents' => $admin ? DB::table('login_events')->latest('id')->limit(15)->get() : collect(),
            'users' => User::query()->where('status', 'active')->whereHas('organizations', fn ($q) => $q->where('organizations.id', $org))->orderBy('name')->get(['id', 'name']),
            'openIncidentCount' => DB::table('security_incidents')->where('organization_id', $org)->whereNotIn('status', ['resolved', 'closed'])->count(),
            'failedJobCount' => $failedJobs,
            'queuedJobCount' => $queuedJobs,
            'pendingReviews' => $admin ? DB::table('access_review_items')->whereNull('decision')->count() : null,
        ]);
    }

    public function incidentStore(Request $request)
    {
        $this->authorizeSecurity($request, 'security.incidents.manage');
        $org = (int) $request->user()->active_organization_id;
        $data = $request->validate(['title' => ['required', 'string', 'max:180'], 'details' => ['required', 'string', 'max:10000'], 'severity' => ['required', Rule::in(['low', 'medium', 'high', 'critical'])], 'assigned_to' => ['nullable', 'integer', Rule::exists('users', 'id')->where('status', 'active')]]);
        if ($data['assigned_to'] ?? null) abort_unless(User::findOrFail($data['assigned_to'])->canAccessOrganization($org), 422, 'The assignee must belong to this organization.');
        $id = DB::table('security_incidents')->insertGetId([...$data, 'organization_id' => $org, 'reference' => 'SEC-'.now()->format('ymd').'-'.strtoupper(bin2hex(random_bytes(3))), 'status' => 'open', 'created_by' => $request->user()->id, 'created_at' => now(), 'updated_at' => now()]);
        $this->audit($request, $org, 'security.incident.opened', 'security_incidents', $id, ['severity' => $data['severity']]);
        return back()->with('success', 'Security incident recorded. Reference: '.DB::table('security_incidents')->where('id', $id)->value('reference'));
    }

    public function incidentUpdate(Request $request, int $incident)
    {
        $this->authorizeSecurity($request, 'security.incidents.manage');
        $org = (int) $request->user()->active_organization_id;
        $record = DB::table('security_incidents')->where('organization_id', $org)->where('id', $incident)->first();
        abort_unless($record, 404);
        $data = $request->validate(['status' => ['required', Rule::in(['open', 'acknowledged', 'investigating', 'resolved', 'closed'])], 'resolution_notes' => ['nullable', 'string', 'max:5000']]);
        DB::table('security_incidents')->where('id', $incident)->update([...$data, 'resolved_by' => in_array($data['status'], ['resolved', 'closed'], true) ? $request->user()->id : null, 'resolved_at' => in_array($data['status'], ['resolved', 'closed'], true) ? now() : null, 'updated_at' => now()]);
        $this->audit($request, $org, 'security.incident.updated', 'security_incidents', $incident, ['status' => $data['status']]);
        return back()->with('success', 'Incident status updated.');
    }

    public function backupStore(Request $request)
    {
        $this->authorizeSecurity($request, 'security.backups.manage');
        $org = (int) $request->user()->active_organization_id;
        $data = $request->validate(['backup_type' => ['required', Rule::in(['database', 'files', 'full'])], 'label' => ['required', 'string', 'max:160'], 'status' => ['required', Rule::in(['completed', 'failed'])], 'storage_location' => ['required', 'string', 'max:255'], 'checksum' => ['nullable', 'string', 'max:128'], 'size_bytes' => ['nullable', 'integer', 'min:0'], 'backup_at' => ['required', 'date', 'before_or_equal:now'], 'notes' => ['nullable', 'string', 'max:3000']]);
        $id = DB::table('security_backup_records')->insertGetId([...$data, 'organization_id' => $org, 'restore_result' => 'not_tested', 'recorded_by' => $request->user()->id, 'created_at' => now(), 'updated_at' => now()]);
        $this->audit($request, $org, 'security.backup.recorded', 'security_backup_records', $id, ['backup_type' => $data['backup_type'], 'status' => $data['status']]);
        return back()->with('success', 'Backup record saved. The backup itself must be created in the approved host or storage platform.');
    }

    public function backupVerify(Request $request, int $backup)
    {
        $this->authorizeSecurity($request, 'security.backups.manage');
        $org = (int) $request->user()->active_organization_id;
        $record = DB::table('security_backup_records')->where('organization_id', $org)->where('id', $backup)->first();
        abort_unless($record, 404);
        $data = $request->validate(['restore_result' => ['required', Rule::in(['passed', 'failed'])], 'notes' => ['nullable', 'string', 'max:3000']]);
        DB::table('security_backup_records')->where('id', $backup)->update(['restore_result' => $data['restore_result'], 'restore_tested_at' => now(), 'notes' => $data['notes'] ?? $record->notes, 'updated_at' => now()]);
        $this->audit($request, $org, 'security.backup.restore_test_recorded', 'security_backup_records', $backup, ['restore_result' => $data['restore_result']]);
        return back()->with('success', 'Restore test result recorded. This entry records an operator-run restore drill; it does not restore data.');
    }

    public function retentionUpdate(Request $request, int $policy)
    {
        $this->authorizeSecurity($request, 'security.retention.manage');
        $record = DB::table('security_retention_policies')->whereNull('organization_id')->where('id', $policy)->first();
        abort_unless($record, 404);
        $data = $request->validate(['retention_days' => ['required', 'integer', 'between:1,3650'], 'enabled' => ['required', 'boolean']]);
        DB::table('security_retention_policies')->where('id', $policy)->update(['retention_days' => $data['retention_days'], 'enabled' => (bool) $data['enabled'], 'updated_by' => $request->user()->id, 'updated_at' => now()]);
        $this->audit($request, (int) $request->user()->active_organization_id, 'security.retention.updated', 'security_retention_policies', $policy, $data);
        return back()->with('success', 'Retention policy saved. Data is deleted only when an authorized operator runs the retention command with --apply.');
    }

    public function classificationUpdate(Request $request, int $classification)
    {
        $this->authorizeSecurity($request, 'security.manage');
        $record = DB::table('security_classifications')->where('id', $classification)->first();
        abort_unless($record, 404);
        $data = $request->validate(['classification' => ['required', Rule::in(['public', 'internal', 'confidential', 'restricted'])], 'handling_notes' => ['nullable', 'string', 'max:2000']]);
        DB::table('security_classifications')->where('id', $classification)->update([...$data, 'updated_by' => $request->user()->id, 'updated_at' => now()]);
        $this->audit($request, (int) $request->user()->active_organization_id, 'security.classification.updated', 'security_classifications', $classification, $data);
        return back()->with('success', 'Data classification saved. Existing module permissions remain the access controls.');
    }

    public function rotationStore(Request $request)
    {
        $this->authorizeSecurity($request, 'security.manage');
        $org = (int) $request->user()->active_organization_id;
        $data = $request->validate(['system' => ['required', 'string', 'max:60'], 'secret_label' => ['required', 'string', 'max:120'], 'owner_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('status', 'active')], 'last_rotated_at' => ['nullable', 'date', 'before_or_equal:now'], 'next_due_at' => ['nullable', 'date', 'after:today'], 'notes' => ['nullable', 'string', 'max:2000']]);
        if ($data['owner_id'] ?? null) abort_unless(User::findOrFail($data['owner_id'])->canAccessOrganization($org), 422, 'The owner must belong to this organization.');
        $id = DB::table('security_rotation_records')->insertGetId([...$data, 'organization_id' => $org, 'status' => 'tracked', 'recorded_by' => $request->user()->id, 'created_at' => now(), 'updated_at' => now()]);
        $this->audit($request, $org, 'security.secret_rotation.tracked', 'security_rotation_records', $id, ['system' => $data['system'], 'secret_label' => $data['secret_label'], 'next_due_at' => $data['next_due_at'] ?? null]);
        return back()->with('success', 'Rotation schedule recorded. Never enter the secret value here.');
    }

    public function recoveryPlanSave(Request $request)
    {
        $this->authorizeSecurity($request, 'security.manage');
        $org = (int) $request->user()->active_organization_id;
        $data = $request->validate(['owner_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('status', 'active')], 'rpo_minutes' => ['required', 'integer', 'between:1,525600'], 'rto_minutes' => ['required', 'integer', 'between:1,525600'], 'last_restore_test_at' => ['nullable', 'date', 'before_or_equal:now'], 'next_review_on' => ['nullable', 'date'], 'notes' => ['nullable', 'string', 'max:3000']]);
        if ($data['owner_id'] ?? null) abort_unless(User::findOrFail($data['owner_id'])->canAccessOrganization($org), 422, 'The plan owner must belong to this organization.');
        $createdAt = DB::table('security_recovery_plans')->where('organization_id', $org)->value('created_at') ?: now();
        DB::table('security_recovery_plans')->updateOrInsert(['organization_id' => $org], [...$data, 'updated_by' => $request->user()->id, 'updated_at' => now(), 'created_at' => $createdAt]);
        $this->audit($request, $org, 'security.recovery_plan.updated', 'security_recovery_plans', $org, ['rpo_minutes' => $data['rpo_minutes'], 'rto_minutes' => $data['rto_minutes'], 'next_review_on' => $data['next_review_on'] ?? null]);
        return back()->with('success', 'Recovery objectives saved. Confirm them against the host backup and restoration process.');
    }

    private function authorizeSecurity(Request $request, string $permission): void
    {
        abort_unless(app(Modules::class)->enabled('security'), 404);
        $user = $request->user();
        abort_unless($user && ($user->isSuperAdmin() || $user->can($permission)), 403);
        abort_unless($user->active_organization_id && $user->canAccessOrganization((int) $user->active_organization_id), 403);
    }

    private function audit(Request $request, int $org, string $event, string $type, int $id, array $new): void
    {
        AuditEvent::create(['organization_id' => $org, 'actor_id' => $request->user()->id, 'event' => $event, 'auditable_type' => $type, 'auditable_id' => $id, 'new_values' => $new, 'ip_address' => $request->ip(), 'user_agent' => $request->userAgent(), 'created_at' => now()]);
    }

    private function settingTimestamp(string $key): ?\Illuminate\Support\Carbon
    {
        $value = DB::table('system_settings')->where('key', $key)->value('value');
        $decoded = is_string($value) ? json_decode($value, true) : $value;
        $value = is_array($decoded) ? ($decoded['value'] ?? null) : $decoded;
        return is_string($value) && strtotime($value) ? \Illuminate\Support\Carbon::parse($value) : null;
    }

    private function health(int $failedJobs, int $queuedJobs, ?\Illuminate\Support\Carbon $queueBeat, ?\Illuminate\Support\Carbon $scheduleBeat): array
    {
        try { DB::connection()->getPdo(); $database = 'healthy'; } catch (\Throwable) { $database = 'unavailable'; }
        return [
            ['name' => 'Database', 'status' => $database, 'detail' => config('database.default').' connection'],
            ['name' => 'Application storage', 'status' => is_writable(storage_path()) && is_writable(storage_path('framework')) ? 'healthy' : 'attention', 'detail' => 'Private storage and framework cache directories'],
            ['name' => 'Application key', 'status' => filled(config('app.key')) ? 'healthy' : 'attention', 'detail' => 'Key configured; value is never displayed'],
            ['name' => 'Queue backlog', 'status' => $failedJobs > 0 || $queuedJobs > 1000 ? 'attention' : 'healthy', 'detail' => $queuedJobs.' queued · '.$failedJobs.' failed'],
            ['name' => 'Queue worker heartbeat', 'status' => $queueBeat && $queueBeat->gt(now()->subMinutes(10)) ? 'healthy' : 'attention', 'detail' => $queueBeat?->diffForHumans() ?? 'No recent worker heartbeat'],
            ['name' => 'Scheduler heartbeat', 'status' => $scheduleBeat && $scheduleBeat->gt(now()->subMinutes(10)) ? 'healthy' : 'attention', 'detail' => $scheduleBeat?->diffForHumans() ?? 'No recent scheduler heartbeat'],
            ['name' => 'Debug mode', 'status' => config('app.debug') ? 'attention' : 'healthy', 'detail' => config('app.debug') ? 'Enabled; disable for production' : 'Disabled'],
        ];
    }
}
