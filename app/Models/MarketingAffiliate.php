<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketingAffiliate extends Model
{
    protected $fillable = ['organization_id', 'name', 'code', 'email', 'approved_commission_percent', 'notes', 'is_active', 'created_by'];
    protected function casts(): array { return ['approved_commission_percent' => 'decimal:2', 'is_active' => 'boolean']; }
}
