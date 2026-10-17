<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Contact extends Model
{
    protected $fillable = ['organization_id', 'account_id', 'first_name', 'last_name', 'email', 'phone', 'created_by'];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function account()
    {
        return $this->belongsTo(Account::class);
    }
}
