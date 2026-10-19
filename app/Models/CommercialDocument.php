<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class CommercialDocument extends Model
{
    protected $fillable = ['contract_id','onboarding_item_id','name','document_path','client_visible','uploaded_by'];
    protected function casts(): array { return ['client_visible'=>'boolean']; }
    public function contract(): BelongsTo { return $this->belongsTo(CommercialContract::class,'contract_id'); }
    public function onboardingItem(): BelongsTo { return $this->belongsTo(CommercialOnboardingItem::class,'onboarding_item_id'); }
}
