<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SmsConsent extends Model
{
    protected $fillable = ['organization_id', 'phone', 'name', 'source', 'status', 'consented_at', 'opted_out_at', 'updated_by'];

    protected function casts(): array
    {
        return ['consented_at' => 'datetime', 'opted_out_at' => 'datetime'];
    }
}
