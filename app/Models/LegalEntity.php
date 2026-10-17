<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LegalEntity extends Model
{
    protected $fillable = ['name', 'code', 'registration_number', 'tax_number', 'email', 'phone', 'address', 'currency', 'timezone', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function organizations()
    {
        return $this->hasMany(Organization::class);
    }
}
