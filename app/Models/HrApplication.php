<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HrApplication extends Model
{
    protected $fillable = ['organization_id','vacancy_id','candidate_id','stage','cover_letter','resume_path','screening_notes','stage_history','applied_at','hired_employee_id'];
    protected function casts(): array { return ['cover_letter' => 'encrypted','screening_notes' => 'encrypted','stage_history' => 'encrypted:array','applied_at' => 'datetime']; }
    public function vacancy(): BelongsTo { return $this->belongsTo(HrVacancy::class, 'vacancy_id'); }
    public function candidate(): BelongsTo { return $this->belongsTo(HrCandidate::class, 'candidate_id'); }
    public function interviews(): HasMany { return $this->hasMany(HrInterview::class, 'application_id'); }
    public function assessments(): HasMany { return $this->hasMany(HrCandidateAssessment::class, 'application_id'); }
    public function offers(): HasMany { return $this->hasMany(HrOffer::class, 'application_id'); }
}
