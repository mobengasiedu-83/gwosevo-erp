<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Account extends Model
{
    protected $fillable = ['reference', 'organization_id', 'name', 'email', 'phone', 'industry', 'created_by'];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }
}
