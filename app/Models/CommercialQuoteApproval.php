<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class CommercialQuoteApproval extends Model
{
    protected $fillable = ['status','justification','decision_note','requested_by','decided_by','decided_at'];
    protected function casts(): array { return ['decided_at'=>'datetime']; }
    public function quote(): BelongsTo { return $this->belongsTo(CommercialQuote::class,'quote_id'); }
    public function requester(): BelongsTo { return $this->belongsTo(User::class,'requested_by'); }
    public function decider(): BelongsTo { return $this->belongsTo(User::class,'decided_by'); }
}
