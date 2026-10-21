<?php
namespace App\Domain\Commercial;
use App\Models\AuditEvent;
use App\Models\CommercialQuote;
use App\Models\CommercialQuoteApproval;
use App\Models\Opportunity;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class QuoteService
{
    public function create(Opportunity $opportunity, array $data, User $actor): CommercialQuote
    {
        return DB::transaction(function () use ($opportunity, $data, $actor) {
            $opportunity=Opportunity::query()->lockForUpdate()->findOrFail($opportunity->id);
            $version = ((int) CommercialQuote::where('opportunity_id', $opportunity->id)->max('version')) + 1;
            $quote = CommercialQuote::create(['organization_id'=>$opportunity->organization_id,'opportunity_id'=>$opportunity->id,'version'=>$version,'reference'=>'QTE-TMP-'.Str::uuid(),'currency'=>$data['currency'],'valid_until'=>$data['valid_until']??null,'notes'=>$data['notes']??null,'discount_percent'=>$data['discount_percent']??0,'created_by'=>$actor->id]);
            $quote->update(['reference'=>'QTE-'.str_pad((string)$quote->id,6,'0',STR_PAD_LEFT)]);
            $this->replaceLines($quote, $data['lines']);
            $this->audit($quote, 'quote.created', null, ['reference'=>$quote->reference,'version'=>$version], $actor);
            return $quote->load('lines');
        });
    }
    public function update(CommercialQuote $quote, array $data, User $actor): void
    {
        DB::transaction(function () use ($quote, $data, $actor) {
            if ($quote->status !== 'draft') throw ValidationException::withMessages(['quote'=>'Only draft versions can be edited.']);
            $old=$quote->only(['currency','valid_until','notes','discount_percent','subtotal','total']);
            $quote->update(['currency'=>$data['currency'],'valid_until'=>$data['valid_until']??null,'notes'=>$data['notes']??null,'discount_percent'=>$data['discount_percent']??0]);
            $this->replaceLines($quote,$data['lines']);
            $this->audit($quote,'quote.updated',$old,$quote->only(array_keys($old)),$actor);
        });
    }
    public function newVersion(CommercialQuote $source, User $actor): CommercialQuote
    {
        return DB::transaction(function () use ($source,$actor) {
            $data=['currency'=>$source->currency,'valid_until'=>$source->valid_until?->toDateString(),'notes'=>$source->notes,'discount_percent'=>$source->discount_percent,'lines'=>$source->lines->map(fn($line)=>$line->only(['service_line_id','service_package_id','price_list_item_id','description','pricing_type','quantity','unit_price','billing_period']))->all()];
            return $this->create($source->opportunity,$data,$actor);
        });
    }
    public function submit(CommercialQuote $quote, string $justification, User $actor): void
    {
        DB::transaction(function () use ($quote,$justification,$actor) {
            $quote=CommercialQuote::query()->lockForUpdate()->findOrFail($quote->id);
            if ($quote->status !== 'draft' || ! $quote->lines()->exists()) throw ValidationException::withMessages(['quote'=>'A draft with at least one priced line is required.']);
            if ($quote->valid_until && $quote->valid_until->lt(today())) throw ValidationException::withMessages(['valid_until'=>'Expired quotations cannot be submitted for approval. Create a new version with a current validity date.']);
            $quote->update(['status'=>'pending_approval']);
            CommercialQuoteApproval::create(['quote_id'=>$quote->id,'requested_by'=>$actor->id,'status'=>'pending','justification'=>$justification]);
            $this->audit($quote,'quote.approval.requested',null,['justification'=>$justification],$actor);
        });
    }
    public function decide(CommercialQuote $quote, string $decision, ?string $note, User $actor): void
    {
        DB::transaction(function () use ($quote,$decision,$note,$actor) {
            $quote=CommercialQuote::query()->lockForUpdate()->findOrFail($quote->id);
            if ($quote->status!=='pending_approval') throw ValidationException::withMessages(['decision'=>'This quotation no longer has a pending decision.']);
            $approval=$quote->approvals()->where('status','pending')->lockForUpdate()->latest()->firstOrFail();
            if ((int)$approval->requested_by === (int)$actor->id) throw ValidationException::withMessages(['decision'=>'The requester cannot decide their own quote approval.']);
            $approval->update(['status'=>$decision,'decided_by'=>$actor->id,'decision_note'=>$note,'decided_at'=>now()]);
            $quote->update(['status'=>$decision]);
            $this->audit($quote,'quote.approval.'.$decision,['status'=>'pending_approval'],['status'=>$decision,'decision_note'=>$note],$actor);
        });
    }
    private function replaceLines(CommercialQuote $quote, array $lines): void
    {
        $quote->lines()->delete(); $subtotal=0;
        foreach ($lines as $line) {
            $amount=round((float)$line['quantity']*(float)$line['unit_price'],2); $subtotal += $amount;
            $quote->lines()->create([...$line,'line_total'=>$amount]);
        }
        $total=round($subtotal*(1-(float)$quote->discount_percent/100),2);
        $quote->update(['subtotal'=>round($subtotal,2),'total'=>$total]);
    }
    private function audit(CommercialQuote $quote,string $event,?array $old,array $new,User $actor): void
    {
        AuditEvent::create(['organization_id'=>$quote->organization_id,'actor_id'=>$actor->id,'event'=>$event,'auditable_type'=>CommercialQuote::class,'auditable_id'=>$quote->id,'old_values'=>$old,'new_values'=>$new,'ip_address'=>request()->ip(),'user_agent'=>request()->userAgent()]);
    }
}
