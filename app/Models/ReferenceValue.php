<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReferenceValue extends Model
{
    public const CATEGORIES = ['country', 'region', 'industry', 'customer_category'];

    protected $fillable = ['category', 'code', 'label', 'parent_code', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
