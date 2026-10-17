<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Opportunity extends Model
{
    protected $fillable = ['organization_id', 'account_id', 'contact_id', 'lead_id', 'name', 'stage', 'amount', 'probability', 'expected_close_date', 'owner_id', 'created_by'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'expected_close_date' => 'date'];
    }
}
