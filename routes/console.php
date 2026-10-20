<?php

use App\Domain\ContactCentre\VitalPbxCallHistory;
use App\Domain\Finance\FinanceService;
use App\Domain\Identity\AccessReviews;
use App\Jobs\HealthHeartbeat;
use App\Console\Commands\SendLeadFollowUpAlerts;
use App\Console\Commands\CaptureOpportunityForecast;
use App\Console\Commands\SendOpportunityActionAlerts;
use App\Console\Commands\SendContractRenewalAlerts;
use App\Models\AccessReview;
use App\Models\PbxConnection;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\SmsCampaign;
use App\Models\SmsConsent;
use App\Models\SmsMessage;
use App\Models\HrEmployee;
use App\Jobs\SendSmsMessage;
use App\Notifications\AutomationNotification;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

Schedule::command('tickets:check-sla')->everyFiveMinutes()->withoutOverlapping();

Artisan::command('sms:dispatch-scheduled', function () {
    $dispatchedCampaigns = 0;
    SmsCampaign::where('status', 'scheduled')->whereNotNull('scheduled_at')->where('scheduled_at', '<=', now())
        ->orderBy('id')->chunkById(50, function ($campaigns) {
            foreach ($campaigns as $campaign) {
                $changed = SmsCampaign::whereKey($campaign->id)->where('status', 'scheduled')->update(['status' => 'queued', 'started_at' => now()]);
                if (! $changed) continue;
                $messageIds = $campaign->messages()->where('status', 'queued')->pluck('id');
                $messageIds->each(fn ($id) => SendSmsMessage::dispatch($id)->onQueue('sms'));
                $dispatchedCampaigns++;
                Log::channel('sms')->info('Scheduled SMS campaign dispatched.', ['campaign_id' => $campaign->id, 'organization_id' => $campaign->organization_id, 'message_count' => $messageIds->count()]);
            }
        });
    Log::channel('sms')->info('Scheduled SMS campaign dispatch scan completed.', ['campaigns_dispatched' => $dispatchedCampaigns]);
    $this->info('Due SMS campaigns have been dispatched to the sms queue.');
})->purpose('Queue due SMS campaigns');

Schedule::command('sms:dispatch-scheduled')->everyMinute()->withoutOverlapping();

Artisan::command('automation:send-reminders', function () {
    $sent = 0;
    DB::table('automation_approval_requests')->where('status', 'pending')->whereNotNull('due_at')->where('due_at', '<=', now())
        ->where(fn ($query) => $query->whereNull('last_reminded_at')->orWhere('last_reminded_at', '<=', now()->subDay()))
        ->orderBy('id')->chunkById(100, function ($requests) use (&$sent) {
            foreach ($requests as $request) {
                $steps = DB::table('automation_approval_steps')->where('request_id', $request->id)->where('stage', $request->current_stage)->where('status', 'pending')->get();
                $rules = DB::table('automation_rules')->where('organization_id', $request->organization_id)->where('event_key', 'approval.overdue')->where('is_active', true)->get();
                $matchingRules = collect($rules)->filter(function ($rule) use ($request) {
                    if (! $rule->condition_field) return true;
                    $actual = (string) ($request->{$rule->condition_field} ?? '');
                    $expected = (string) ($rule->condition_value ?? '');
                    return match ($rule->condition_operator) {
                        'not_equals' => $actual !== $expected,
                        'contains' => str_contains(mb_strtolower($actual), mb_strtolower($expected)),
                        default => $actual === $expected,
                    };
                });
                $recipients = $matchingRules->isEmpty() ? $steps->pluck('approver_id')->all() : $matchingRules->flatMap(fn ($rule) => $rule->action === 'notify_requester' ? [$request->requested_by] : $steps->pluck('approver_id')->all())->all();
                foreach (array_unique($recipients) as $userId) {
                    $user = User::find($userId);
                    if (! $user) continue;
                    $preference = DB::table('automation_preferences')->where('organization_id', $request->organization_id)->where('user_id', $userId)->where('event_key', 'automation.reminder')->first();
                    $inApp = $preference?->in_app ?? true;
                    $email = $preference?->email ?? false;
                    $body = 'Approval “'.$request->title.'” is overdue. Please review it in the ERP.';
                    if ($inApp) {
                        $user->notify(new AutomationNotification('Approval overdue', $body, route('automation.index'), 'automation.reminder', $request->organization_id));
                        DB::table('automation_delivery_logs')->insert(['organization_id' => $request->organization_id, 'user_id' => $userId, 'event_key' => 'automation.reminder', 'channel' => 'in_app', 'recipient' => $user->email, 'status' => 'sent', 'attempts' => 1, 'metadata' => json_encode(['approval_request_id' => $request->id]), 'sent_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
                        $sent++;
                    }
                    if ($email) {
                        try {
                            Mail::raw($body.' '.route('automation.index'), fn ($mail) => $mail->to($user->email)->subject('Approval overdue: '.$request->title));
                            $status = 'sent'; $failure = null;
                        } catch (Throwable $exception) {
                            $status = 'failed'; $failure = mb_substr($exception->getMessage(), 0, 1000);
                        }
                        DB::table('automation_delivery_logs')->insert(['organization_id' => $request->organization_id, 'user_id' => $userId, 'event_key' => 'automation.reminder', 'channel' => 'email', 'recipient' => $user->email, 'status' => $status, 'attempts' => 1, 'failure_reason' => $failure, 'metadata' => json_encode(['approval_request_id' => $request->id]), 'sent_at' => $status === 'sent' ? now() : null, 'created_at' => now(), 'updated_at' => now()]);
                        if ($status === 'sent') $sent++;
                    }
                    if (($preference?->sms ?? false)) {
                        $phone = HrEmployee::query()->where('organization_id', $request->organization_id)->where('user_id', $userId)->value('work_phone');
                        $phone = $phone ? preg_replace('/[^0-9+]/', '', $phone) : null;
                        $configured = filled(config('services.txtconnect.key')) && filled(config('services.txtconnect.sender')) && str_starts_with((string) config('services.txtconnect.base_url'), 'https://');
                        $consented = $phone && SmsConsent::query()->where('organization_id', $request->organization_id)->where('phone', $phone)->where('status', 'opted_in')->exists();
                        if (! $configured || ! $phone || ! $consented || ! app(\App\Domain\Administration\Modules::class)->enabled('sms')) {
                            $status = 'failed'; $failure = 'SMS needs a work phone, active TXTConnect configuration, enabled SMS module, and current opt-in consent.';
                        } else {
                            try {
                                $message = SmsMessage::create(['organization_id' => $request->organization_id, 'to' => $phone, 'sender' => config('services.txtconnect.sender'), 'body' => mb_substr($body, 0, 1600), 'status' => 'queued', 'idempotency_key' => (string) Str::uuid(), 'created_by' => null]);
                                SendSmsMessage::dispatch($message->id)->onQueue('sms');
                                $status = 'queued'; $failure = null;
                            } catch (Throwable $exception) {
                                $status = 'failed'; $failure = mb_substr($exception->getMessage(), 0, 1000);
                            }
                        }
                        DB::table('automation_delivery_logs')->insert(['organization_id' => $request->organization_id, 'user_id' => $userId, 'event_key' => 'automation.reminder', 'channel' => 'sms', 'recipient' => $phone, 'status' => $status, 'attempts' => 1, 'failure_reason' => $failure, 'metadata' => json_encode(['approval_request_id' => $request->id]), 'sent_at' => $status === 'queued' ? now() : null, 'created_at' => now(), 'updated_at' => now()]);
                        if ($status === 'queued') $sent++;
                    }
                }
                DB::table('automation_approval_requests')->where('id', $request->id)->update(['last_reminded_at' => now(), 'updated_at' => now()]);
            }
        });
    $this->info("Sent {$sent} approval reminder(s).");
})->purpose('Send reminders for overdue approval requests');

Schedule::command('automation:send-reminders')->hourly()->withoutOverlapping();

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('vitalpbx:check', function () {
    $baseUrl = rtrim((string) config('vitalpbx.base_url'), '/');
    $apiKey = (string) config('vitalpbx.api_key');

    if ($baseUrl === '' || $apiKey === '') {
        $this->error('Set VITALPBX_API_BASE_URL and VITALPBX_API_KEY in .env first.');

        return self::FAILURE;
    }

    if (parse_url($baseUrl, PHP_URL_SCHEME) !== 'https') {
        $this->error('VitalPBX API base URL must use HTTPS.');

        return self::FAILURE;
    }

    try {
        $request = Http::acceptJson()
            ->withHeader('app-key', $apiKey)
            ->connectTimeout(min(5, (int) config('vitalpbx.timeout')))
            ->timeout((int) config('vitalpbx.timeout'));

        if (filled(config('vitalpbx.tenant'))) {
            $request->withHeader('tenant', (string) config('vitalpbx.tenant'));
        }

        $response = $request->get($baseUrl.'/api/v2/tenants');
    } catch (\Throwable $exception) {
        report($exception);
        $this->error('VitalPBX API request failed. Check the URL, TLS certificate, firewall, and Laravel log.');

        return self::FAILURE;
    }

    if (! $response->successful()) {
        $this->error('VitalPBX API returned HTTP '.$response->status().'. Check the API key, tenant, and API access rules.');

        return self::FAILURE;
    }

    $this->info('VitalPBX API responded successfully over HTTPS.');

    return self::SUCCESS;
})->purpose('Check the configured VitalPBX API credentials and HTTPS access');

Artisan::command('vitalpbx:sync-calls', function (VitalPbxCallHistory $callHistory) {
    $connections = PbxConnection::where('driver', 'vitalpbx')->orderBy('id')->get();
    if ($connections->isEmpty()) {
        $this->info('No VitalPBX profiles are configured.');

        return self::SUCCESS;
    }

    $failed = false;
    foreach ($connections as $connection) {
        try {
            $result = $callHistory->sync($connection);
            $this->info(sprintf('%s: %d new, %d updated, %d skipped.', $connection->name, $result['imported'], $result['updated'], $result['skipped']));
        } catch (\Throwable $exception) {
            report($exception);
            $this->error($connection->name.': call history sync failed. Check the API credentials, CDR permissions, and Laravel log.');
            $failed = true;
        }
    }

    return $failed ? self::FAILURE : self::SUCCESS;
})->purpose('Import completed call records from configured VitalPBX profiles');

Schedule::call(function () {
    SystemSetting::updateOrCreate(['key' => 'scheduler_last_seen'], ['value' => now()->toIso8601String()]);
    HealthHeartbeat::dispatch();
})->everyMinute()->name('erp-health-heartbeat')->withoutOverlapping();

Artisan::command('identity:review-due', function () {
    $interval = max(7, min(365, config('identity.review_interval_days')));
    if (AccessReview::whereNull('completed_at')->exists()) {
        $this->info('An access review is already open.');

        return;
    }
    $last = AccessReview::latest('created_at')->first();
    if ($last && $last->created_at->gt(now()->subDays($interval))) {
        $this->info('The next access review is not due.');

        return;
    }
    $admin = User::role('Administrator')->where('status', 'active')->orderBy('id')->first();
    if (! $admin) {
        $this->error('No active administrator is available.');

        return 1;
    }
    app(AccessReviews::class)->create('Periodic access review '.now()->toDateString(), $admin, $admin, now()->addDays(14)->toDateTimeString());
    $this->info('Periodic access review created.');
})->purpose('Create an access review when the configured recertification interval is due');

Schedule::command('identity:review-due')->daily()->withoutOverlapping();

Schedule::command(SendLeadFollowUpAlerts::class)->everyFifteenMinutes()->withoutOverlapping();
Schedule::command(CaptureOpportunityForecast::class)->weeklyOn(1, '07:00')->withoutOverlapping();
Schedule::command(SendOpportunityActionAlerts::class)->everyFifteenMinutes()->withoutOverlapping();
Schedule::command(SendContractRenewalAlerts::class)->daily()->withoutOverlapping();
Schedule::command('vitalpbx:sync-calls')->everyFiveMinutes()->withoutOverlapping();

Artisan::command('finance:generate-recurring', function (FinanceService $finance) {
    $count = $finance->generateRecurringInvoices();
    $this->info("Generated {$count} recurring invoice(s) for independent approval.");
})->purpose('Generate due recurring finance invoices');

Schedule::command('finance:generate-recurring')->daily()->withoutOverlapping();

Artisan::command('security:prune-retained-data {--apply : Permanently delete eligible records} {--force : Skip the interactive confirmation}', function () {
    $targets = ['audit_events' => 'created_at', 'login_events' => 'created_at', 'integration_health_logs' => 'checked_at', 'failed_jobs' => 'failed_at'];
    $policies = DB::table('security_retention_policies')->whereNull('organization_id')->where('enabled', true)->orderBy('category')->get();
    if ($policies->isEmpty()) {
        $this->info('No retention policies are enabled. No records were changed.');
        return self::SUCCESS;
    }
    $plan = [];
    foreach ($policies as $policy) {
        if (! isset($targets[$policy->category])) {
            $this->warn("Skipping unsupported retention category: {$policy->category}");
            continue;
        }
        $cutoff = now()->subDays((int) $policy->retention_days);
        $count = DB::table($policy->category)->where($targets[$policy->category], '<', $cutoff)->count();
        $plan[] = [$policy->category, (int) $policy->retention_days, $count, $cutoff];
    }
    $this->table(['Category', 'Keep days', 'Eligible rows', 'Cutoff'], array_map(fn ($row) => [$row[0], $row[1], $row[2], $row[3]->toDateTimeString()], $plan));
    if (! $this->option('apply')) {
        $this->info('Preview only. Add --apply to delete these records.');
        return self::SUCCESS;
    }
    if (! $this->option('force') && ! $this->confirm('Permanently delete the eligible records shown above?')) {
        $this->warn('No records were changed.');
        return self::SUCCESS;
    }
    foreach ($plan as [$category, $days, $count, $cutoff]) {
        $deleted = DB::table($category)->where($targets[$category], '<', $cutoff)->delete();
        DB::table('security_retention_runs')->insert(['category' => $category, 'deleted_rows' => $deleted, 'cutoff_at' => $cutoff, 'run_at' => now(), 'run_by' => get_current_user() ?: 'cli']);
        Log::channel('daily')->notice('Security retention purge completed.', ['category' => $category, 'deleted_rows' => $deleted, 'cutoff_at' => $cutoff->toIso8601String()]);
        $this->info("{$category}: {$deleted} row(s) deleted.");
    }
})->purpose('Preview or manually purge expired audit and operational records under enabled retention policies');
