<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HrAssessmentTemplate extends Model
{
    protected $fillable = ['organization_id','name','assessment_type','instructions','questions','duration_minutes','passing_score','is_active','created_by'];
    protected function casts(): array { return ['instructions' => 'encrypted','questions' => 'encrypted:array','is_active' => 'boolean']; }
    public function assignments(): HasMany { return $this->hasMany(HrCandidateAssessment::class, 'template_id'); }
}
