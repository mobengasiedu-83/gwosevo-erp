<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
class CommercialQuote extends Model
{
    protected $fillable = ['organization_id','opportunity_id','version','reference','status','currency','valid_until','notes','subtotal','discount_percent','total','created_by'];
    protected function casts(): array { return ['valid_until'=>'date','subtotal'=>'decimal:2','discount_percent'=>'decimal:2','total'=>'decimal:2']; }
    public function opportunity(): BelongsTo { return $this->belongsTo(Opportunity::class); }
    public function lines(): HasMany { return $this->hasMany(CommercialQuoteLine::class,'quote_id'); }
    public function approvals(): HasMany { return $this->hasMany(CommercialQuoteApproval::class,'quote_id'); }
}
