<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesTarget extends Model
{
    protected $fillable = ['organization_id', 'user_id', 'team_id', 'period_start', 'period_end', 'revenue_target', 'pipeline_target', 'created_by'];
    protected function casts(): array { return ['period_start' => 'date', 'period_end' => 'date', 'revenue_target' => 'decimal:2', 'pipeline_target' => 'decimal:2']; }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function team(): BelongsTo { return $this->belongsTo(Team::class); }
}
