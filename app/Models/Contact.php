<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Contact extends Model
{
    protected $fillable = ['organization_id', 'account_id', 'first_name', 'last_name', 'email', 'phone', 'preferred_channel', 'email_opt_in', 'sms_opt_in', 'created_by'];

    protected function casts(): array
    {
        return ['email_opt_in' => 'boolean', 'sms_opt_in' => 'boolean'];
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function account()
    {
        return $this->belongsTo(Account::class);
    }
}
