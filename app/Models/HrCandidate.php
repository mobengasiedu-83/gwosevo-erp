<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HrCandidate extends Model
{
    protected $fillable = ['organization_id','profile','consent_at'];
    protected function casts(): array { return ['profile' => 'encrypted:array','consent_at' => 'datetime']; }
    public function applications(): HasMany { return $this->hasMany(HrApplication::class, 'candidate_id'); }
}
