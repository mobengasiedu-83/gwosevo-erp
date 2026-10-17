<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Position extends Model
{
    protected $fillable = ['organization_id', 'department_id', 'reports_to_position_id', 'code', 'title', 'grade', 'job_description', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function reportsTo()
    {
        return $this->belongsTo(self::class, 'reports_to_position_id');
    }
}
