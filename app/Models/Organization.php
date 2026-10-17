<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Organization extends Model
{
    protected $fillable = ['name', 'code', 'email', 'phone', 'timezone', 'is_active', 'legal_entity_id', 'parent_id', 'address'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'user_organization_memberships');
    }

    public function legalEntity()
    {
        return $this->belongsTo(LegalEntity::class);
    }

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function memberships()
    {
        return $this->hasMany(OrganizationMembership::class);
    }

    public function positions()
    {
        return $this->hasMany(Position::class);
    }

    public function costCentres()
    {
        return $this->hasMany(CostCentre::class);
    }

    public function departments(): HasMany
    {
        return $this->hasMany(Department::class);
    }

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    public function teams(): HasMany
    {
        return $this->hasMany(Team::class);
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }
}
