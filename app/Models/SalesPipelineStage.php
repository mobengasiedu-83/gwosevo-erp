<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesPipelineStage extends Model
{
    protected $fillable = ['organization_id', 'key', 'name', 'probability', 'sort_order', 'is_closed', 'is_won', 'is_active'];

    protected function casts(): array
    {
        return ['is_closed' => 'boolean', 'is_won' => 'boolean', 'is_active' => 'boolean'];
    }
}
