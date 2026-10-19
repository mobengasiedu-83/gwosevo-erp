<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OpportunityStageHistory extends Model
{
    public $timestamps = false;
    protected $fillable = ['opportunity_id', 'from_stage', 'to_stage', 'notes', 'changed_by', 'changed_at'];
    protected function casts(): array { return ['changed_at' => 'datetime']; }
    public function actor(): BelongsTo { return $this->belongsTo(User::class, 'changed_by'); }
}
