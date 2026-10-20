<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HrRequisition extends Model
{
    protected $fillable = ['organization_id','reference','title','department_id','position_id','headcount','justification','status','requested_by','decided_by','decided_at'];
    protected function casts(): array { return ['justification' => 'encrypted','decided_at' => 'datetime']; }
    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function vacancies(): HasMany { return $this->hasMany(HrVacancy::class, 'requisition_id'); }
}
