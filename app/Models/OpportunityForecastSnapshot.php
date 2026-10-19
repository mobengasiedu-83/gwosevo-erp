<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OpportunityForecastSnapshot extends Model
{
    protected $fillable = ['opportunity_id', 'organization_id', 'snapshot_date', 'forecast_month', 'amount', 'probability', 'weighted_amount'];
    protected function casts(): array { return ['snapshot_date' => 'date', 'forecast_month' => 'date', 'amount' => 'decimal:2', 'weighted_amount' => 'decimal:2']; }
}
