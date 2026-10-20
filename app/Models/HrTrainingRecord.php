<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HrTrainingRecord extends Model
{
    protected $fillable = ['organization_id','employee_id','course_id','status','started_on','completed_on','expires_on','certificate_document_id','recorded_by'];
    protected function casts(): array { return ['started_on' => 'date','completed_on' => 'date','expires_on' => 'date']; }
    public function employee() { return $this->belongsTo(HrEmployee::class, 'employee_id'); }
    public function course() { return $this->belongsTo(HrTrainingCourse::class, 'course_id'); }
    public function certificate() { return $this->belongsTo(HrEmployeeDocument::class, 'certificate_document_id'); }
}
