<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OpportunityDiscountApproval extends Model
{
    protected $fillable = ['opportunity_id', 'discount_percent', 'terms', 'justification', 'status', 'requested_by', 'decided_by', 'decision_note', 'decided_at'];
    protected function casts(): array { return ['discount_percent' => 'decimal:2', 'decided_at' => 'datetime']; }
    public function requester(): BelongsTo { return $this->belongsTo(User::class, 'requested_by'); }
    public function decider(): BelongsTo { return $this->belongsTo(User::class, 'decided_by'); }
    public function opportunity(): BelongsTo { return $this->belongsTo(Opportunity::class, 'opportunity_id'); }
}
