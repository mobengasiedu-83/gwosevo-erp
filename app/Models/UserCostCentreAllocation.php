<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserCostCentreAllocation extends Model
{
    protected $fillable = ['membership_id', 'cost_centre_id', 'percentage'];

    protected function casts(): array
    {
        return ['percentage' => 'decimal:2'];
    }

    public function membership()
    {
        return $this->belongsTo(OrganizationMembership::class, 'membership_id');
    }

    public function costCentre()
    {
        return $this->belongsTo(CostCentre::class);
    }
}
