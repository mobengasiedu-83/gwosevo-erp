<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrCandidateAssessment extends Model
{
    protected $fillable = ['organization_id','application_id','template_id','access_token_hash','started_at','expires_at','submitted_at','responses','artifact_path','score','assessor_notes','status','assessed_by'];
    protected function casts(): array { return ['started_at' => 'datetime','expires_at' => 'datetime','submitted_at' => 'datetime','responses' => 'encrypted:array','assessor_notes' => 'encrypted']; }
    public function application(): BelongsTo { return $this->belongsTo(HrApplication::class, 'application_id'); }
    public function template(): BelongsTo { return $this->belongsTo(HrAssessmentTemplate::class, 'template_id'); }
}
