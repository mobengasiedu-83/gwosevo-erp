<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HrVacancy extends Model
{
    protected $fillable = ['organization_id','requisition_id','slug','title','description','requirements','opens_on','closes_on','status','created_by'];
    protected function casts(): array { return ['description' => 'encrypted','requirements' => 'encrypted','opens_on' => 'date','closes_on' => 'date']; }
    public function requisition(): BelongsTo { return $this->belongsTo(HrRequisition::class, 'requisition_id'); }
    public function applications(): HasMany { return $this->hasMany(HrApplication::class, 'vacancy_id'); }
}
