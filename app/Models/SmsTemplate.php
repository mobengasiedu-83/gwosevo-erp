<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SmsTemplate extends Model
{
    protected $fillable = ['organization_id', 'name', 'category', 'body', 'is_active', 'created_by'];
}
