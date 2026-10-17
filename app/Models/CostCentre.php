<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CostCentre extends Model
{
    protected $fillable = ['organization_id', 'department_id', 'parent_id', 'manager_id', 'code', 'name', 'kind', 'currency', 'budget', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'budget' => 'decimal:2'];
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function manager()
    {
        return $this->belongsTo(User::class, 'manager_id');
    }
}
