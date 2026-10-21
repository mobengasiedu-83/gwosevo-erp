<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommercialPriceList extends Model
{
    protected $fillable = ['organization_id','name','currency','effective_from','effective_until','status','notes','created_by','reviewed_by','reviewed_at','review_note'];

    protected function casts(): array { return ['effective_from'=>'date','effective_until'=>'date','reviewed_at'=>'datetime']; }

    public function items(): HasMany { return $this->hasMany(CommercialPriceListItem::class,'price_list_id'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class,'created_by'); }
    public function reviewer(): BelongsTo { return $this->belongsTo(User::class,'reviewed_by'); }
}
