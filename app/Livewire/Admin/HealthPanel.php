<?php

namespace App\Livewire\Admin;

use App\Domain\Administration\AdminAudit;
use App\Domain\Administration\Settings;
use App\Domain\Administration\SystemHealth;
use App\Jobs\HealthHeartbeat;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;

class HealthPanel extends Component
{
    public function probe(): void
    {
        Gate::authorize('administer-system');
        abort_if(RateLimiter::tooManyAttempts('health-probe:'.auth()->id(), 6), 429);
        RateLimiter::hit('health-probe:'.auth()->id(), 60);
        HealthHeartbeat::dispatch();
        app(Settings::class)->refresh();
        AdminAudit::record('admin.health.probe', 'system_settings', 0, null, ['queue' => config('queue.default')]);
        session()->flash('probe_message', 'Probe dispatched. Worker heartbeat updates after the job runs.');
    }

    public function render()
    {
        Gate::authorize('administer-system');

        return view('livewire.admin.health-panel', ['checks' => app(SystemHealth::class)->checks()]);
    }
}
