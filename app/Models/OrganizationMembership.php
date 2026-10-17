<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrganizationMembership extends Model
{
    protected $fillable = ['user_id', 'organization_id', 'department_id', 'team_id', 'scope', 'branch_id', 'position_id', 'manager_id', 'approval_manager_id'];

    protected function casts(): array
    {
        return [];
    }

    protected $table = 'user_organization_memberships';

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function position()
    {
        return $this->belongsTo(Position::class);
    }

    public function manager()
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function approvalManager()
    {
        return $this->belongsTo(User::class, 'approval_manager_id');
    }

    public function allocations()
    {
        return $this->hasMany(UserCostCentreAllocation::class, 'membership_id');
    }
}
