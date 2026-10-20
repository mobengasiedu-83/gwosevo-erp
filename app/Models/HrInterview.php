<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrInterview extends Model
{
    protected $fillable = ['organization_id','application_id','scheduled_at','format','status','score','panel_notes','interviewer_id'];
    protected function casts(): array { return ['scheduled_at' => 'datetime','panel_notes' => 'encrypted']; }
    public function application(): BelongsTo { return $this->belongsTo(HrApplication::class, 'application_id'); }
}
