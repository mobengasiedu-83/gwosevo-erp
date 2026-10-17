<?php

namespace App\Domain\Administration;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class SystemHealth
{
    public function checks(): array
    {
        $checks = [];
        foreach ([
            'Database' => function () {
                DB::select('SELECT 1');

                return 'Connected';
            },
            'Storage' => function () {
                $path = 'health/'.Str::uuid();
                try {
                    if (! Storage::disk('local')->put($path, 'ok') || Storage::disk('local')->get($path) !== 'ok') {
                        throw new \RuntimeException;
                    }
                } finally {
                    Storage::disk('local')->delete($path);
                }

                return 'Private storage is writable';
            },
            'Cache' => function () {
                $key = 'health:'.Str::uuid();
                try {
                    Cache::put($key, 'ok', 30);
                    if (Cache::get($key) !== 'ok') {
                        throw new \RuntimeException;
                    }
                } finally {
                    Cache::forget($key);
                }

                return 'Read/write succeeded';
            },
            'Queue connection' => fn () => config('queue.default') === 'sync' ? 'Synchronous execution; no background worker' : Queue::size().' pending jobs',
        ] as $label => $check) {
            try {
                $checks[$label] = ['ok' => true, 'message' => $check()];
            } catch (Throwable $e) {
                report($e);
                $checks[$label] = ['ok' => false, 'message' => 'Check failed. Review server logs.'];
            }
        }
        $settings = app(Settings::class);
        foreach (['Scheduler' => 'scheduler_last_seen', 'Queue worker' => 'queue_last_seen'] as $label => $key) {
            $last = $settings->get($key);
            $fresh = $last && now()->diffInMinutes(Carbon::parse($last), true) < 5;
            $checks[$label] = ['ok' => (bool) $fresh, 'message' => $last ? 'Last heartbeat: '.$settings->formatDate($last, true) : 'No heartbeat recorded'];
        }
        if (config('queue.default') === 'sync') {
            $checks['Queue worker'] = ['ok' => false, 'message' => 'Queue is synchronous; background worker is not configured.'];
        }

        return $checks;
    }
}
