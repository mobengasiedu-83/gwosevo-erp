<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommercialPriceListItem extends Model
{
    protected $fillable = ['price_list_id','service_line_id','service_package_id','description','pricing_type','unit_price','billing_period'];
    protected function casts(): array { return ['unit_price'=>'decimal:2']; }
    public function priceList(): BelongsTo { return $this->belongsTo(CommercialPriceList::class,'price_list_id'); }
    public function serviceLine(): BelongsTo { return $this->belongsTo(SalesServiceLine::class,'service_line_id'); }
    public function servicePackage(): BelongsTo { return $this->belongsTo(SalesServicePackage::class,'service_package_id'); }
}
