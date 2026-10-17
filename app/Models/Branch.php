<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Branch extends Model
{
    protected $fillable = ['organization_id', 'name', 'code', 'address', 'phone', 'is_active', 'manager_id', 'email', 'city', 'country_code'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function manager()
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }
}
