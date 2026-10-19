<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $indexes = collect(Schema::getIndexes('opportunity_forecast_snapshots'))->pluck('name');
        if (! $indexes->contains('opp_forecast_snap_uniq')) {
            Schema::table('opportunity_forecast_snapshots', fn (Blueprint $table) => $table->unique(['opportunity_id', 'snapshot_date'], 'opp_forecast_snap_uniq'));
        }
        if (! $indexes->contains('opp_forecast_month_idx')) {
            Schema::table('opportunity_forecast_snapshots', fn (Blueprint $table) => $table->index(['organization_id', 'forecast_month', 'snapshot_date'], 'opp_forecast_month_idx'));
        }
    }

    public function down(): void
    {
        Schema::table('opportunity_forecast_snapshots', function (Blueprint $table) {
            $table->dropUnique('opp_forecast_snap_uniq');
            $table->dropIndex('opp_forecast_month_idx');
        });
    }
};
