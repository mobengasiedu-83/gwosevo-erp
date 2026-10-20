<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HrTrainingCourse extends Model
{
    protected $fillable = ['organization_id','name','provider','description','is_active','created_by'];
    protected function casts(): array { return ['is_active' => 'boolean']; }
    public function records() { return $this->hasMany(HrTrainingRecord::class, 'course_id'); }
}
