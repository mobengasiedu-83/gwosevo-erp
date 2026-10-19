<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class CommercialHandover extends Model
{
    protected $fillable = ['contract_id','submitted_by','accepted_by','status','notes','submitted_at','accepted_at'];
    protected function casts(): array { return ['submitted_at'=>'datetime','accepted_at'=>'datetime']; }
    public function contract(): BelongsTo { return $this->belongsTo(CommercialContract::class,'contract_id'); }
}
