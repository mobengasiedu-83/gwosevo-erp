<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
class CommercialOnboardingItem extends Model
{
    protected $fillable = ['title','notes','status','client_visible','assigned_to','completed_by','completed_at'];
    protected function casts(): array { return ['client_visible'=>'boolean','completed_at'=>'datetime']; }
    public function contract(): BelongsTo { return $this->belongsTo(CommercialContract::class,'contract_id'); }
    public function documents(): HasMany { return $this->hasMany(CommercialDocument::class,'onboarding_item_id'); }
}
