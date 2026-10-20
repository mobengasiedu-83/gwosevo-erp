<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrOffer extends Model
{
    protected $fillable = ['organization_id','application_id','terms','valid_until','status','created_by','approved_by','approved_at','response_token_hash','responded_at'];
    protected function casts(): array { return ['terms' => 'encrypted','valid_until' => 'date','approved_at' => 'datetime','responded_at' => 'datetime']; }
    public function application(): BelongsTo { return $this->belongsTo(HrApplication::class, 'application_id'); }
}
