<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesServicePackage extends Model
{
    protected $fillable = ['organization_id', 'service_line_id', 'name', 'code', 'description', 'is_active', 'sort_order'];

    public function line(): BelongsTo
    {
        return $this->belongsTo(SalesServiceLine::class, 'service_line_id');
    }
}
