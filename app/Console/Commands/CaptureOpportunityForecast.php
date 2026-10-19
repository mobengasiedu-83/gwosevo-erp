<?php

namespace App\Console\Commands;

use App\Models\Opportunity;
use App\Models\OpportunityForecastSnapshot;
use Illuminate\Console\Command;

class CaptureOpportunityForecast extends Command
{
    protected $signature = 'sales:forecast-snapshot';
    protected $description = 'Capture a dated weighted pipeline forecast for future accuracy reporting';

    public function handle(): int
    {
        $date = today();
        $count = 0;
        Opportunity::whereNotIn('stage', ['won', 'lost'])->whereNotNull('expected_close_date')->orderBy('id')->chunkById(200, function ($opportunities) use ($date, &$count) {
            foreach ($opportunities as $opportunity) {
                OpportunityForecastSnapshot::updateOrCreate(
                    ['opportunity_id' => $opportunity->id, 'snapshot_date' => $date->toDateString()],
                    ['organization_id' => $opportunity->organization_id, 'forecast_month' => $opportunity->expected_close_date->copy()->startOfMonth()->toDateString(), 'amount' => $opportunity->amount, 'probability' => $opportunity->probability, 'weighted_amount' => round((float) $opportunity->amount * $opportunity->probability / 100, 2)]
                );
                $count++;
            }
        });
        $this->info("Captured {$count} opportunity forecast snapshots.");
        return self::SUCCESS;
    }
}
