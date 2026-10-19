<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class CommercialQuoteLine extends Model
{
    protected $fillable = ['service_line_id','service_package_id','description','pricing_type','quantity','unit_price','billing_period','line_total'];
    protected function casts(): array { return ['quantity'=>'decimal:3','unit_price'=>'decimal:2','line_total'=>'decimal:2']; }
    public function quote(): BelongsTo { return $this->belongsTo(CommercialQuote::class,'quote_id'); }
    public function serviceLine(): BelongsTo { return $this->belongsTo(SalesServiceLine::class,'service_line_id'); }
    public function servicePackage(): BelongsTo { return $this->belongsTo(SalesServicePackage::class,'service_package_id'); }
}
