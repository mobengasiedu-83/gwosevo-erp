<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeadAssignmentCursor extends Model
{
    protected $primaryKey = 'organization_id';
    public $incrementing = false;
    protected $fillable = ['organization_id', 'last_owner_id'];
}
