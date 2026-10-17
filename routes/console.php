<?php

use App\Domain\Identity\AccessReviews;
use App\Jobs\HealthHeartbeat;
use App\Models\AccessReview;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

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
