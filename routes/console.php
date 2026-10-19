<?php

use App\Domain\ContactCentre\VitalPbxCallHistory;
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
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schedule;

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
