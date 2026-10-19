<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
class CommercialContract extends Model
{
    protected $fillable = ['organization_id','opportunity_id','quote_id','renewal_opportunity_id','template_id','reference','title','status','client_name','client_email','start_date','end_date','renewal_date','renewal_alert_sent_at','document_path','client_contract_visible','sla_terms','deliverables','created_by'];
    protected function casts(): array { return ['start_date'=>'date','end_date'=>'date','renewal_date'=>'date','renewal_alert_sent_at'=>'datetime','client_contract_visible'=>'boolean']; }
    public function opportunity(): BelongsTo { return $this->belongsTo(Opportunity::class); }
    public function quote(): BelongsTo { return $this->belongsTo(CommercialQuote::class,'quote_id'); }
    public function template(): BelongsTo { return $this->belongsTo(CommercialContractTemplate::class,'template_id'); }
    public function onboardingItems(): HasMany { return $this->hasMany(CommercialOnboardingItem::class,'contract_id'); }
    public function documents(): HasMany { return $this->hasMany(CommercialDocument::class,'contract_id'); }
    public function handover() { return $this->hasOne(CommercialHandover::class,'contract_id'); }
    public function renewalOpportunity(): BelongsTo { return $this->belongsTo(Opportunity::class,'renewal_opportunity_id'); }
}
