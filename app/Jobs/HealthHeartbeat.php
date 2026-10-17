<?php

namespace App\Jobs;

use App\Models\SystemSetting;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class HealthHeartbeat implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        SystemSetting::updateOrCreate(['key' => 'queue_last_seen'], ['value' => now()->toIso8601String()]);
    }
}
