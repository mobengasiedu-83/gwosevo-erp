<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OpportunityCompetitor extends Model
{
    protected $fillable = ['opportunity_id', 'name', 'notes'];
}
