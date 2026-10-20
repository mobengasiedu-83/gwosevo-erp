<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HrEmployee extends Model
{
    protected $fillable = ['organization_id','user_id','employee_number','first_name','last_name','work_email','work_phone','personal_data','employment_type','status','hire_date','end_date','department_id','team_id','branch_id','position_id','manager_employee_id','created_by'];
    protected function casts(): array { return ['personal_data' => 'encrypted:array','hire_date' => 'date','end_date' => 'date']; }
    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function department(): BelongsTo { return $this->belongsTo(Department::class); }
    public function team(): BelongsTo { return $this->belongsTo(Team::class); }
    public function branch(): BelongsTo { return $this->belongsTo(Branch::class); }
    public function position(): BelongsTo { return $this->belongsTo(Position::class); }
    public function manager(): BelongsTo { return $this->belongsTo(self::class, 'manager_employee_id'); }
    public function reports(): HasMany { return $this->hasMany(self::class, 'manager_employee_id'); }
    public function contracts(): HasMany { return $this->hasMany(HrEmploymentContract::class, 'employee_id'); }
    public function documents(): HasMany { return $this->hasMany(HrEmployeeDocument::class, 'employee_id'); }
    public function history(): HasMany { return $this->hasMany(HrJobHistory::class, 'employee_id')->orderByDesc('effective_on'); }
    public function tasks(): HasMany { return $this->hasMany(HrLifecycleTask::class, 'employee_id'); }
    public function cases(): HasMany { return $this->hasMany(HrDisciplinaryCase::class, 'employee_id'); }
    public function trainingRecords(): HasMany { return $this->hasMany(HrTrainingRecord::class, 'employee_id'); }
}
