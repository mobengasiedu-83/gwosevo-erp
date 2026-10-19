<?php
namespace App\Http\Controllers\Commercial;

use App\Domain\Administration\Modules;
use App\Domain\Commercial\CommercialAccess;
use App\Domain\Commercial\QuoteService;
use App\Domain\Sales\SalesAccess;
use App\Http\Controllers\Controller;
use App\Models\CommercialContract;
use App\Models\CommercialContractTemplate;
use App\Models\CommercialDocument;
use App\Models\CommercialHandover;
use App\Models\CommercialOnboardingItem;
use App\Models\CommercialQuote;
use App\Models\Opportunity;
use App\Models\SalesServiceLine;
use App\Models\SalesServicePackage;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CommercialController extends Controller
{
    private function enabled(): void { abort_unless(app(Modules::class)->enabled('contracts') && app(Modules::class)->enabled('sales'),404); }
    private function contractAccess(Request $request, CommercialContract $contract, string $permission='contracts.view'): void
    {
        $this->enabled(); abort_unless($request->user()->isActive() && $request->user()->can($permission) && app(CommercialAccess::class)->allowsContract($request->user(),$contract),403);
    }
    private function quoteAccess(Request $request, CommercialQuote $quote, string $ability='view'): void { $this->enabled(); $this->authorize($ability,$quote); }

    public function quotes(Request $request, CommercialAccess $access)
    {
        $this->enabled(); $this->authorize('viewAny',CommercialQuote::class);
        $quotes=$access->quotes($request->user())->with('opportunity.account')->latest()->paginate(20);
        return view('commercial.quotes.index',compact('quotes'));
    }
    public function quoteCreate(Request $request, Opportunity $opportunity)
    {
        $this->enabled(); $this->authorize('create',CommercialQuote::class);
        abort_unless(app(SalesAccess::class)->allows($request->user(),$opportunity),404);
        return view('commercial.quotes.form',['quote'=>new CommercialQuote(['currency'=>config('app.currency','GHS')]),'opportunity'=>$opportunity,'action'=>route('commercial.quotes.store',$opportunity),'method'=>'POST',...$this->catalogOptions($opportunity->organization_id)]);
    }
    public function quoteStore(Request $request, Opportunity $opportunity, QuoteService $service)
    {
        $this->enabled(); $this->authorize('create',CommercialQuote::class);
        abort_unless(app(SalesAccess::class)->allows($request->user(),$opportunity),404);
        $quote=$service->create($opportunity,$this->quoteData($request),$request->user());
        return to_route('commercial.quotes.show',$quote)->with('success','Draft quotation created.');
    }
    public function quoteShow(Request $request, CommercialQuote $quote)
    {
        $this->quoteAccess($request,$quote); $quote->load(['opportunity.account','opportunity.contact','lines.serviceLine','lines.servicePackage','approvals.requester','approvals.decider']);
        $contract=CommercialContract::where('quote_id',$quote->id)->first();
        return view('commercial.quotes.show',compact('quote','contract'));
    }
    public function quoteEdit(Request $request, CommercialQuote $quote)
    {
        $this->quoteAccess($request,$quote,'update'); $quote->load('lines');
        return view('commercial.quotes.form',['quote'=>$quote,'opportunity'=>$quote->opportunity,'action'=>route('commercial.quotes.update',$quote),'method'=>'PUT',...$this->catalogOptions($quote->organization_id)]);
    }
    public function quoteUpdate(Request $request, CommercialQuote $quote, QuoteService $service)
    {
        $this->quoteAccess($request,$quote,'update'); $service->update($quote,$this->quoteData($request),$request->user());
        return to_route('commercial.quotes.show',$quote)->with('success','Draft quotation updated.');
    }
    public function quoteVersion(Request $request, CommercialQuote $quote, QuoteService $service)
    {
        $this->quoteAccess($request,$quote); abort_unless($request->user()->can('quotes.update') && in_array($quote->status,['approved','rejected'],true),403); $version=$service->newVersion($quote,$request->user());
        return to_route('commercial.quotes.edit',$version)->with('success','New quotation version created.');
    }
    public function quoteSubmit(Request $request, CommercialQuote $quote, QuoteService $service)
    {
        $this->quoteAccess($request,$quote,'update'); $data=$request->validate(['justification'=>['required','string','max:3000']]);
        $service->submit($quote,$data['justification'],$request->user());
        return back()->with('success','Quotation submitted for independent approval.');
    }
    public function quoteDecide(Request $request, CommercialQuote $quote, QuoteService $service)
    {
        $this->quoteAccess($request,$quote,'approve'); $data=$request->validate(['decision'=>['required',Rule::in(['approved','rejected'])],'decision_note'=>['nullable','string','max:3000']]);
        $service->decide($quote,$data['decision'],$data['decision_note']??null,$request->user());
        return back()->with('success','Quotation decision recorded.');
    }
    public function quotePrint(Request $request, CommercialQuote $quote)
    {
        $this->quoteAccess($request,$quote); $quote->load(['opportunity.account','opportunity.contact','lines']);
        return view('commercial.quotes.print',compact('quote'));
    }
    public function contracts(Request $request, CommercialAccess $access)
    {
        $this->enabled(); abort_unless($request->user()->can('contracts.view'),403);
        $contracts=$access->contracts($request->user())->with('opportunity.account')->latest()->paginate(20);
        return view('commercial.contracts.index',compact('contracts'));
    }
    public function renewalNotificationRead(Request $request, string $notification)
    {
        $this->enabled(); $item=$request->user()->unreadNotifications()->whereKey($notification)->where('type',\App\Notifications\ContractRenewalNotification::class)->firstOrFail();
        $contract=CommercialContract::findOrFail($item->data['contract_id']??0); $this->contractAccess($request,$contract);
        $item->markAsRead(); return to_route('commercial.contracts.show',$contract);
    }
    public function contractFromQuote(Request $request, CommercialQuote $quote)
    {
        $this->quoteAccess($request,$quote); abort_unless($request->user()->can('contracts.create'),403);
        $created=false;
        $contract=DB::transaction(function() use ($quote,$request,&$created) {
            $quote=CommercialQuote::query()->lockForUpdate()->with('opportunity.account','opportunity.contact')->findOrFail($quote->id);
            abort_unless($quote->status==='approved' && (!$quote->valid_until || !$quote->valid_until->lt(today())),409);
            if ($existing=CommercialContract::where('quote_id',$quote->id)->first()) return $existing;
            $op=$quote->opportunity;
            $contract=CommercialContract::create(['organization_id'=>$quote->organization_id,'opportunity_id'=>$op->id,'quote_id'=>$quote->id,'reference'=>'CON-TMP-'.Str::uuid(),'title'=>$op->name,'status'=>'draft','client_name'=>$op->contact?->first_name ? trim($op->contact->first_name.' '.$op->contact->last_name) : ($op->account?->name ?? 'Client'),'client_email'=>$op->contact?->email ?? $op->account?->email,'created_by'=>$request->user()->id]);
            $contract->update(['reference'=>'CON-'.str_pad((string)$contract->id,6,'0',STR_PAD_LEFT)]); $created=true;
            return $contract;
        });
        if ($created) \App\Domain\Administration\AdminAudit::record('contract.created',CommercialContract::class,$contract->id,null,['reference'=>$contract->reference,'quote_id'=>$quote->id],$quote->organization_id);
        return to_route('commercial.contracts.show',$contract)->with('success','Contract draft created from approved quote.');
    }
    public function contractShow(Request $request, CommercialContract $contract)
    {
        $this->contractAccess($request,$contract); $contract->load(['opportunity.account','quote.lines','template','onboardingItems.documents','documents','handover']);
        $templates=CommercialContractTemplate::where('organization_id',$contract->organization_id)->where('is_active',true)->orderBy('name')->get();
        $tokens=DB::table('commercial_portal_tokens')->where('contract_id',$contract->id)->whereNull('revoked_at')->where('expires_at','>',now())->orderByDesc('id')->get();
        return view('commercial.contracts.show',compact('contract','templates','tokens'));
    }
    public function contractUpdate(Request $request, CommercialContract $contract)
    {
        $this->contractAccess($request,$contract,'contracts.update');
        abort_if(in_array($contract->status,['terminated','expired'],true),409);
        $data=$request->validate(['title'=>['required','string','max:255'],'client_name'=>['required','string','max:255'],'client_email'=>['nullable','email','max:255'],'template_id'=>['nullable',Rule::exists('commercial_contract_templates','id')->where('organization_id',$contract->organization_id)],'start_date'=>['nullable','date'],'end_date'=>['nullable','date','after_or_equal:start_date'],'renewal_date'=>['nullable','date'],'sla_terms'=>['nullable','string','max:10000'],'deliverables'=>['nullable','string','max:10000'],'client_contract_visible'=>['nullable','boolean'],'status'=>['required',Rule::in(['draft','active','terminated','expired'])],'signed_document'=>['nullable','file','mimes:pdf,docx,doc','max:20480']]);
        $data['client_contract_visible']=$request->boolean('client_contract_visible');
        if ($data['status']==='active') abort_unless(($contract->document_path || $request->hasFile('signed_document')) && !empty($data['start_date']) && !empty($data['end_date']),422,'An executed contract file and start/end dates are required before activation.');
        $old=$contract->only(array_keys(collect($data)->except('signed_document')->all())); unset($data['signed_document']);
        if (array_key_exists('renewal_date',$data) && $contract->renewal_date?->toDateString() !== ($data['renewal_date'] ?? null)) $data['renewal_alert_sent_at']=null;
        if ($request->hasFile('signed_document')) {
            if ($contract->document_path) Storage::disk('local')->delete($contract->document_path);
            $data['document_path']=$request->file('signed_document')->store('commercial/contracts','local');
        }
        $contract->update($data);
        \App\Domain\Administration\AdminAudit::record('contract.updated',CommercialContract::class,$contract->id,$old,$contract->only(array_keys($old)),$contract->organization_id);
        return back()->with('success','Contract details saved.');
    }
    public function onboardingAdd(Request $request, CommercialContract $contract)
    {
        $this->contractAccess($request,$contract,'contracts.onboarding.manage');
        $data=$request->validate(['title'=>['required','string','max:255'],'notes'=>['nullable','string','max:3000'],'assigned_to'=>['nullable',Rule::exists('user_organization_memberships','user_id')->where('organization_id',$contract->organization_id)],'client_visible'=>['nullable','boolean']]);
        $item=$contract->onboardingItems()->create([...$data,'client_visible'=>$request->boolean('client_visible')]);
        \App\Domain\Administration\AdminAudit::record('contract.onboarding.item.created',CommercialOnboardingItem::class,$item->id,null,$data,$contract->organization_id);
        return back()->with('success','Onboarding checklist item added.');
    }
    public function onboardingUpdate(Request $request, CommercialContract $contract, CommercialOnboardingItem $item)
    {
        $this->contractAccess($request,$contract,'contracts.onboarding.manage'); abort_unless($item->contract_id===$contract->id,404);
        $data=$request->validate(['status'=>['required',Rule::in(['pending','in_progress','complete','approved','rejected'])],'notes'=>['nullable','string','max:3000'],'client_visible'=>['required','boolean']]);
        if (in_array($data['status'],['approved','rejected'],true)) abort_unless($request->user()->can('contracts.update') && $item->status==='complete' && (int)$item->completed_by !== (int)$request->user()->id,403,'Checklist approval must be completed by a different user.');
        $old=$item->only(array_keys($data)); $data['completed_at']=in_array($data['status'],['complete','approved'],true)?now():null; $data['completed_by']=$data['completed_at']?$request->user()->id:null;
        $item->update($data);
        \App\Domain\Administration\AdminAudit::record('contract.onboarding.item.updated',CommercialOnboardingItem::class,$item->id,$old,$data,$contract->organization_id);
        return back()->with('success','Onboarding checklist updated.');
    }
    public function documentUpload(Request $request, CommercialContract $contract)
    {
        $this->contractAccess($request,$contract,'contracts.onboarding.manage');
        $data=$request->validate(['name'=>['required','string','max:255'],'onboarding_item_id'=>['nullable',Rule::exists('commercial_onboarding_items','id')->where('contract_id',$contract->id)],'client_visible'=>['nullable','boolean'],'document'=>['required','file','mimes:pdf,docx,doc,png,jpg,jpeg','max:20480']]);
        $path=$request->file('document')->store('commercial/documents','local');
        $doc=$contract->documents()->create(['onboarding_item_id'=>$data['onboarding_item_id']??null,'name'=>$data['name'],'document_path'=>$path,'client_visible'=>$request->boolean('client_visible'),'uploaded_by'=>$request->user()->id]);
        \App\Domain\Administration\AdminAudit::record('contract.document.uploaded',CommercialDocument::class,$doc->id,null,['name'=>$doc->name,'client_visible'=>$doc->client_visible],$contract->organization_id);
        return back()->with('success','Document uploaded to private storage.');
    }
    public function documentDownload(Request $request, CommercialDocument $document)
    {
        $contract=$document->contract; $this->contractAccess($request,$contract);
        return Storage::disk('local')->download($document->document_path,$document->name);
    }
    public function contractDownload(Request $request, CommercialContract $contract)
    {
        $this->contractAccess($request,$contract); abort_unless($contract->document_path,404);
        return Storage::disk('local')->download($contract->document_path,$contract->reference.'-executed-contract');
    }
    public function handoverSubmit(Request $request, CommercialContract $contract)
    {
        $this->contractAccess($request,$contract,'contracts.handover'); abort_unless($contract->status==='active',409);
        abort_if($contract->onboardingItems()->whereNotIn('status',['complete','approved'])->exists(),409,'Complete or approve all onboarding checklist items before handover.');
        $handover=CommercialHandover::updateOrCreate(['contract_id'=>$contract->id],['submitted_by'=>$request->user()->id,'status'=>'pending','notes'=>$request->validate(['notes'=>['nullable','string','max:5000']])['notes']??null,'submitted_at'=>now(),'accepted_by'=>null,'accepted_at'=>null]);
        \App\Domain\Administration\AdminAudit::record('contract.handover.submitted',CommercialHandover::class,$handover->id,null,['status'=>'pending'],$contract->organization_id);
        return back()->with('success','Commercial handover sent to Operations.');
    }
    public function handoverAccept(Request $request, CommercialContract $contract)
    {
        $this->contractAccess($request,$contract,'contracts.handover'); $handover=$contract->handover()->where('status','pending')->firstOrFail(); abort_unless((int)$handover->submitted_by !== (int)$request->user()->id,403);
        $handover->update(['status'=>'accepted','accepted_by'=>$request->user()->id,'accepted_at'=>now()]);
        \App\Domain\Administration\AdminAudit::record('contract.handover.accepted',CommercialHandover::class,$handover->id,['status'=>'pending'],['status'=>'accepted'],$contract->organization_id);
        return back()->with('success','Handover accepted.');
    }
    public function renewalOpportunity(Request $request, CommercialContract $contract)
    {
        $this->contractAccess($request,$contract,'contracts.update'); abort_unless($request->user()->can('opportunities.create'),403);
        abort_unless($contract->status==='active' && $contract->renewal_date && $contract->renewal_date->lte(today()->addDays(180)),409);
        if ($contract->renewal_opportunity_id) return to_route('opportunities.show',$contract->renewal_opportunity_id)->with('status','A renewal opportunity already exists.');
        $source=$contract->opportunity;
        $renewal=app(\App\Domain\Sales\OpportunityService::class)->create(['organization_id'=>$contract->organization_id,'account_id'=>$source->account_id,'contact_id'=>$source->contact_id,'name'=>'Renewal: '.$contract->title,'description'=>'Renewal for contract '.$contract->reference,'amount'=>$source->amount,'owner_id'=>$source->owner_id,'stage'=>'new','expected_close_date'=>$contract->renewal_date->toDateString()],$request->user());
        $contract->update(['renewal_opportunity_id'=>$renewal->id]);
        \App\Domain\Administration\AdminAudit::record('contract.renewal_opportunity.created',CommercialContract::class,$contract->id,null,['opportunity_id'=>$renewal->id],$contract->organization_id);
        return to_route('opportunities.show',$renewal)->with('success','Renewal opportunity created.');
    }
    public function templates(Request $request)
    {
        $this->enabled(); abort_unless($request->user()->can('contracts.templates.manage'),403);
        $templates=CommercialContractTemplate::where('organization_id',$request->user()->active_organization_id)->latest()->get();
        return view('commercial.templates',compact('templates'));
    }
    public function templateStore(Request $request)
    {
        $this->enabled(); abort_unless($request->user()->can('contracts.templates.manage') && $request->user()->canAccessOrganization($request->user()->active_organization_id),403);
        $data=$request->validate(['name'=>['required','string','max:255'],'document'=>['required','file','mimes:pdf,docx,doc','max:20480']]);
        $template=CommercialContractTemplate::create(['organization_id'=>$request->user()->active_organization_id,'name'=>$data['name'],'document_path'=>$request->file('document')->store('commercial/templates','local'),'created_by'=>$request->user()->id]);
        \App\Domain\Administration\AdminAudit::record('contract.template.created',CommercialContractTemplate::class,$template->id,null,['name'=>$template->name],$template->organization_id);
        return back()->with('success','Contract template added.');
    }
    public function portalCreate(Request $request, CommercialContract $contract)
    {
        $this->contractAccess($request,$contract,'contracts.portal.manage'); abort_unless($contract->status==='active',409);
        $data=$request->validate(['expires_days'=>['required','integer','min:1','max:90']]); $token=Str::random(64);
        DB::table('commercial_portal_tokens')->insert(['contract_id'=>$contract->id,'token_hash'=>hash('sha256',$token),'expires_at'=>now()->addDays($data['expires_days']),'created_by'=>$request->user()->id,'created_at'=>now(),'updated_at'=>now()]);
        \App\Domain\Administration\AdminAudit::record('contract.portal_link.created',CommercialContract::class,$contract->id,null,['expires_at'=>now()->addDays($data['expires_days'])->toIso8601String()],$contract->organization_id);
        return back()->with('portal_url',route('commercial.portal',$token))->with('success','Client link created. Copy it now; the token is only stored as a hash.');
    }
    public function portalRevoke(Request $request, CommercialContract $contract, int $token)
    {
        $this->contractAccess($request,$contract,'contracts.portal.manage'); DB::table('commercial_portal_tokens')->where('contract_id',$contract->id)->where('id',$token)->whereNull('revoked_at')->update(['revoked_at'=>now(),'updated_at'=>now()]);
        \App\Domain\Administration\AdminAudit::record('contract.portal_link.revoked',CommercialContract::class,$contract->id,null,['token_id'=>$token],$contract->organization_id);
        return back()->with('success','Client link revoked.');
    }
    public function portal(string $token)
    {
        $this->enabled(); $record=$this->portalRecord($token); abort_unless($record,404);
        $contract=CommercialContract::with(['onboardingItems'=>fn($q)=>$q->where('client_visible',true),'documents'=>fn($q)=>$q->where('client_visible',true)])->where('status','active')->findOrFail($record->contract_id);
        return view('commercial.portal',compact('contract','token'));
    }
    public function portalDocument(string $token, int $document)
    {
        $this->enabled(); $record=$this->portalRecord($token); abort_unless($record && DB::table('commercial_contracts')->where('id',$record->contract_id)->where('status','active')->exists(),404);
        $doc=CommercialDocument::where('contract_id',$record->contract_id)->where('client_visible',true)->findOrFail($document);
        return Storage::disk('local')->download($doc->document_path,$doc->name);
    }
    public function portalContractFile(string $token)
    {
        $this->enabled(); $record=$this->portalRecord($token); abort_unless($record,404);
        $contract=CommercialContract::where('status','active')->where('client_contract_visible',true)->findOrFail($record->contract_id);
        abort_unless($contract->document_path,404);
        return Storage::disk('local')->download($contract->document_path,$contract->reference.'-contract');
    }
    private function portalRecord(string $token): ?object
    {
        return DB::table('commercial_portal_tokens')->where('token_hash',hash('sha256',$token))->whereNull('revoked_at')->where('expires_at','>',now())->first();
    }
    private function quoteData(Request $request): array
    {
        $data=$request->validate(['currency'=>['required','string','size:3','alpha'],'valid_until'=>['nullable','date'],'notes'=>['nullable','string','max:5000'],'discount_percent'=>['required','numeric','min:0','max:100'],'lines'=>['required','array','min:1','max:50'],'lines.*.description'=>['required','string','max:255'],'lines.*.service_line_id'=>['nullable',Rule::exists('sales_service_lines','id')->where('organization_id',$request->user()->active_organization_id)],'lines.*.service_package_id'=>['nullable',Rule::exists('sales_service_packages','id')->where('organization_id',$request->user()->active_organization_id)],'lines.*.pricing_type'=>['required',Rule::in(['fixed','recurring','usage','negotiated'])],'lines.*.quantity'=>['required','numeric','gt:0','max:999999'],'lines.*.unit_price'=>['required','numeric','min:0','max:999999999'],'lines.*.billing_period'=>['nullable','string','max:30']]);
        foreach ($data['lines'] as $index=>$line) if (!empty($line['service_package_id']) && !empty($line['service_line_id'])) {
            $package=SalesServicePackage::where('organization_id',$request->user()->active_organization_id)->findOrFail($line['service_package_id']);
            if ($package->service_line_id && (int)$package->service_line_id!==(int)$line['service_line_id']) abort(422,"Pricing line ".($index+1)." package does not belong to the selected service line.");
        }
        return $data;
    }
    private function catalogOptions(int $organizationId): array
    {
        return ['serviceLines'=>SalesServiceLine::where('organization_id',$organizationId)->where('is_active',true)->orderBy('sort_order')->get(),'servicePackages'=>SalesServicePackage::where('organization_id',$organizationId)->where('is_active',true)->orderBy('sort_order')->get()];
    }
}
