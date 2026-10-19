<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesServiceLine extends Model
{
    protected $fillable = ['organization_id', 'name', 'code', 'description', 'is_active', 'sort_order'];

    public function packages(): HasMany
    {
        return $this->hasMany(SalesServicePackage::class, 'service_line_id');
    }
}
