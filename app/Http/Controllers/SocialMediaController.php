<?php

namespace App\Http\Controllers;

use App\Domain\Administration\Modules;
use App\Domain\CRM\LeadService;
use App\Models\MarketingCampaign;
use App\Models\SmsConsent;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class SocialMediaController extends Controller
{
    private function context(Request $request, string $permission = 'social.view'): int
    {
        abort_unless(app(Modules::class)->enabled('social_media'), 404);
        abort_unless($request->user()->isSuperAdmin() || $request->user()->can($permission), 403);
        $organizationId = (int) $request->user()->active_organization_id;
        abort_unless($organizationId && $request->user()->canAccessOrganization($organizationId), 403);
        return $organizationId;
    }

    private function page(Request $request, string $section, array $data = [], string $permission = 'social.view')
    {
        $this->context($request, $permission);
        return view('social-media.workspace', ['section' => $section, ...$data]);
    }

    public function dashboard(Request $request)
    {
        $org = $this->context($request);
        $stats = [
            'accounts' => DB::table('social_accounts')->where('organization_id',$org)->count(),
            'inbox' => DB::table('social_inbox_items')->where('organization_id',$org)->where('kind','conversation')->whereIn('status',['new','in_progress'])->count(),
            'planned_posts' => DB::table('social_posts')->where('organization_id',$org)->whereNotNull('planned_for')->whereNotIn('status',['rejected'])->count(),
            'pending' => DB::table('social_posts')->where('organization_id',$org)->where('status','pending_approval')->count(),
            'leads' => DB::table('leads')->where('organization_id',$org)->where('source','social')->count(),
        ];
        $posts = DB::table('social_posts')->where('organization_id',$org)->orderByDesc('created_at')->limit(6)->get();
        $inbox = DB::table('social_inbox_items')->where('organization_id',$org)->whereIn('kind',['conversation','comment','mention'])->orderByDesc('received_at')->limit(8)->get();
        return $this->page($request,'Social Media Dashboard',compact('stats','posts','inbox'));
    }

    public function accounts(Request $request)
    {
        $org = $this->context($request);
        $accounts = DB::table('social_accounts')->where('organization_id',$org)->orderBy('platform')->orderBy('account_name')->get();
        return $this->page($request,'Connected Accounts',compact('accounts'));
    }

    public function accountStore(Request $request)
    {
        $org = $this->context($request,'social.accounts.manage');
        $data = $request->validate(['platform'=>['required',Rule::in(['facebook','instagram','linkedin','x','tiktok','youtube','other'])],'account_name'=>['required','string','max:160'],'handle'=>['nullable','string','max:160'],'permission_notes'=>['nullable','string','max:3000']]);
        DB::table('social_accounts')->insert([...$data,'organization_id'=>$org,'connection_status'=>'not_connected','created_by'=>$request->user()->id,'created_at'=>now(),'updated_at'=>now()]);
        return back()->with('success','Social account profile saved as not connected. Platform authorization is not configured.');
    }

    public function inbox(Request $request) { return $this->inboxPage($request,'conversation','Unified Social Inbox'); }
    public function engagement(Request $request) { return $this->inboxPage($request,'comment','Comments & Engagement'); }
    public function listening(Request $request) { return $this->inboxPage($request,'mention','Social Listening'); }

    private function inboxPage(Request $request, string $kind, string $title)
    {
        $org = $this->context($request);
        $items = DB::table('social_inbox_items')->where('organization_id',$org)->where('kind',$kind)->orderByRaw("CASE WHEN status IN ('responded','closed') THEN 1 ELSE 0 END")->orderByDesc('received_at')->paginate(30)->withQueryString();
        $users = $this->members($org);
        return $this->page($request,$title,compact('items','users','kind'));
    }

    public function inboxStore(Request $request)
    {
        $org = $this->context($request,'social.inbox.manage');
        $data = $request->validate(['kind'=>['required',Rule::in(['conversation','comment','mention'])],'platform'=>['required',Rule::in(['facebook','instagram','linkedin','x','tiktok','youtube','other'])],'external_reference'=>['nullable','string','max:180'],'author_name'=>['nullable','string','max:180'],'subject'=>['nullable','string','max:255'],'body'=>['required','string','max:10000'],'received_at'=>['nullable','date'],'tags'=>['nullable','string','max:1000']]);
        $tags = collect(explode(',',(string)($data['tags'] ?? '')))->map(fn($tag)=>trim($tag))->filter()->unique()->values()->all();
        $id = DB::table('social_inbox_items')->insertGetId(['organization_id'=>$org,'kind'=>$data['kind'],'platform'=>$data['platform'],'external_reference'=>$data['external_reference'] ?? null,'author_name'=>$data['author_name'] ?? null,'subject'=>$data['subject'] ?? null,'body'=>$data['body'],'status'=>'new','tags'=>json_encode($tags),'received_at'=>$data['received_at'] ?? now(),'created_by'=>$request->user()->id,'created_at'=>now(),'updated_at'=>now()]);
        $rule = DB::table('social_automation_rules')->where('organization_id',$org)->where('trigger_kind',$data['kind'])->where('is_active',true)->whereNotNull('assign_to_id')->orderBy('id')->get()->first(fn($rule)=>blank($rule->match_text) || str_contains(mb_strtolower($data['body']),mb_strtolower($rule->match_text)));
        if ($rule) DB::table('social_inbox_items')->where('id',$id)->update(['assigned_to_id'=>$rule->assign_to_id,'status'=>'in_progress','updated_at'=>now()]);
        return back()->with('success','Social item saved locally.'.($rule ? ' An active assignment rule assigned it.' : ' No platform event was received or sent.'));
    }

    public function inboxUpdate(Request $request, int $item)
    {
        $org = $this->context($request,'social.inbox.manage');
        $record = DB::table('social_inbox_items')->where('organization_id',$org)->find($item);
        abort_if(! $record,404);
        $data = $request->validate(['status'=>['required',Rule::in(['new','in_progress','responded','escalated','closed'])],'assigned_to_id'=>['nullable','integer',Rule::exists('users','id')->where('status','active')]]);
        if (! empty($data['assigned_to_id'])) abort_unless(User::whereKey($data['assigned_to_id'])->whereHas('organizations',fn($q)=>$q->where('organizations.id',$org))->exists(),422,'Assignee must be an active organization member.');
        DB::table('social_inbox_items')->where('id',$item)->update([...$data,'updated_at'=>now()]);
        return back()->with('success','Social item assignment and status updated.');
    }

    public function reply(Request $request, int $item)
    {
        $org = $this->context($request,'social.inbox.manage');
        $record = DB::table('social_inbox_items')->where('organization_id',$org)->find($item);
        abort_if(! $record,404);
        $data = $request->validate(['response_note'=>['required','string','max:5000']]);
        DB::table('social_inbox_items')->where('id',$item)->update(['response_note'=>$data['response_note'],'status'=>'responded','responded_at'=>now(),'updated_at'=>now()]);
        return back()->with('success','Response recorded internally. It was not sent to the social platform.');
    }

    public function posts(Request $request)
    {
        $org = $this->context($request);
        $posts = DB::table('social_posts')->where('organization_id',$org)->orderByDesc('created_at')->paginate(30)->withQueryString();
        $accounts = DB::table('social_accounts')->where('organization_id',$org)->orderBy('platform')->get();
        $campaigns = MarketingCampaign::where('organization_id',$org)->orderBy('name')->get();
        $users = $this->members($org);
        return $this->page($request,'Posts & Publishing',compact('posts','accounts','campaigns','users'));
    }

    public function postStore(Request $request)
    {
        $org = $this->context($request,'social.posts.manage');
        $data = $request->validate(['title'=>['required','string','max:180'],'body'=>['required','string','max:10000'],'account_id'=>['nullable','integer',Rule::exists('social_accounts','id')->where('organization_id',$org)],'campaign_id'=>['nullable','integer',Rule::exists('marketing_campaigns','id')->where('organization_id',$org)],'owner_id'=>['nullable','integer',Rule::exists('users','id')->where('status','active')],'planned_for'=>['nullable','date','after_or_equal:now']]);
        if (! empty($data['owner_id'])) abort_unless(User::whereKey($data['owner_id'])->whereHas('organizations',fn($q)=>$q->where('organizations.id',$org))->exists(),422,'Post owner must be an active organization member.');
        DB::table('social_posts')->insert(['organization_id'=>$org,'account_id'=>$data['account_id'] ?? null,'campaign_id'=>$data['campaign_id'] ?? null,'title'=>$data['title'],'body'=>$data['body'],'planned_for'=>$data['planned_for'] ?? null,'status'=>'draft','owner_id'=>$data['owner_id'] ?? null,'created_by'=>$request->user()->id,'created_at'=>now(),'updated_at'=>now()]);
        return back()->with('success','Social post saved as a draft. This workspace does not publish posts to platforms.');
    }

    public function postSubmit(Request $request, int $post)
    {
        $org = $this->context($request,'social.posts.manage');
        $record = DB::table('social_posts')->where('organization_id',$org)->find($post);
        abort_if(! $record,404);
        abort_unless(in_array($record->status,['draft','rejected'],true),422,'Only drafts or rejected posts can be submitted.');
        DB::table('social_posts')->where('id',$post)->update(['status'=>'pending_approval','review_comment'=>null,'updated_at'=>now()]);
        return back()->with('success','Post submitted for internal approval.');
    }

    public function postDecision(Request $request, int $post)
    {
        $org = $this->context($request,'social.posts.approve');
        $record = DB::table('social_posts')->where('organization_id',$org)->find($post);
        abort_if(! $record,404);
        abort_unless($record->status==='pending_approval',422,'This post is not awaiting approval.');
        $data = $request->validate(['decision'=>['required',Rule::in(['approved','rejected'])],'review_comment'=>['nullable','string','max:3000']]);
        DB::table('social_posts')->where('id',$post)->update(['status'=>$data['decision'],'reviewed_by'=>$request->user()->id,'review_comment'=>$data['review_comment'] ?? null,'updated_at'=>now()]);
        return back()->with('success','Internal post decision recorded. Approval does not publish the post.');
    }

    public function calendar(Request $request)
    {
        $org=$this->context($request);
        $posts=DB::table('social_posts')->where('organization_id',$org)->whereNotNull('planned_for')->orderBy('planned_for')->limit(200)->get();
        return $this->page($request,'Content Calendar',compact('posts'));
    }

    public function leads(Request $request)
    {
        $org=$this->context($request);
        abort_unless(app(Modules::class)->enabled('crm') && ($request->user()->isSuperAdmin() || $request->user()->can('leads.view')),403,'CRM access is required to view social leads.');
        $leads=DB::table('leads')->where('organization_id',$org)->where('source','social')->orderByDesc('created_at')->paginate(25)->withQueryString();
        return $this->page($request,'Social Leads',compact('leads'));
    }

    public function leadStore(Request $request, LeadService $service)
    {
        $org=$this->context($request,'social.leads.capture');
        abort_unless(app(Modules::class)->enabled('crm') && ($request->user()->isSuperAdmin() || $request->user()->can('leads.create')),403,'CRM lead creation access is required.');
        $data=$request->validate(['first_name'=>['required','string','max:100'],'last_name'=>['required','string','max:100'],'email'=>['nullable','required_without:phone','email','max:255'],'phone'=>['nullable','required_without:email','string','max:50'],'company_name'=>['nullable','string','max:255'],'platform'=>['required',Rule::in(['facebook','instagram','linkedin','x','tiktok','youtube','other'])],'source_detail'=>['nullable','string','max:255'],'email_consent'=>['nullable','accepted'],'sms_consent'=>['nullable','accepted']]);
        $lead=$service->create(['organization_id'=>$org,'first_name'=>$data['first_name'],'last_name'=>$data['last_name'],'email'=>$data['email'] ?? null,'phone'=>$data['phone'] ?? null,'company_name'=>$data['company_name'] ?? null,'source'=>'social','source_detail'=>'Social '.$data['platform'].(!empty($data['source_detail']) ? ': '.$data['source_detail'] : '')],$request->user());
        if (!empty($data['email_consent']) && !empty($data['email'])) DB::table('marketing_consents')->updateOrInsert(['organization_id'=>$org,'channel'=>'email','destination'=>mb_strtolower($data['email'])],['lead_id'=>$lead->id,'status'=>'opted_in','source'=>'Social lead intake','consented_at'=>now(),'withdrawn_at'=>null,'updated_by'=>$request->user()->id,'created_at'=>now(),'updated_at'=>now()]);
        if (!empty($data['sms_consent']) && !empty($data['phone'])) {
            $phone=preg_replace('/[^0-9+]/','',$data['phone']);
            DB::table('marketing_consents')->updateOrInsert(['organization_id'=>$org,'channel'=>'sms','destination'=>$phone],['lead_id'=>$lead->id,'status'=>'opted_in','source'=>'Social lead intake','consented_at'=>now(),'withdrawn_at'=>null,'updated_by'=>$request->user()->id,'created_at'=>now(),'updated_at'=>now()]);
            SmsConsent::updateOrCreate(['organization_id'=>$org,'phone'=>$phone],['name'=>$lead->full_name,'source'=>'Social lead intake','status'=>'opted_in','consented_at'=>now(),'opted_out_at'=>null,'updated_by'=>$request->user()->id]);
        }
        return back()->with('success','Social lead created in CRM. Contact consent was recorded only when explicitly provided.');
    }

    public function campaigns(Request $request)
    {
        $org=$this->context($request);
        $campaigns=MarketingCampaign::where('organization_id',$org)->withCount(['tasks','landingPages'])->latest()->paginate(25)->withQueryString();
        return $this->page($request,'Campaign Management',compact('campaigns'));
    }

    public function assets(Request $request)
    {
        $org=$this->context($request);
        $assets=DB::table('social_assets')->where('organization_id',$org)->orderByDesc('created_at')->paginate(30)->withQueryString();
        return $this->page($request,'Content Library',compact('assets'));
    }

    public function assetStore(Request $request)
    {
        $org=$this->context($request,'social.assets.manage');
        $data=$request->validate(['title'=>['required','string','max:180'],'asset'=>['required','file','max:20480','mimes:jpg,jpeg,png,gif,webp,mp4,pdf,txt'],'tags'=>['nullable','string','max:1000'],'usage_rights'=>['required','string','max:3000']]);
        $file=$data['asset'];$path=$file->store('social-media/'.$org,'local');
        DB::table('social_assets')->insert(['organization_id'=>$org,'title'=>$data['title'],'original_name'=>basename($file->getClientOriginalName()),'mime_type'=>$file->getMimeType() ?: 'application/octet-stream','size_bytes'=>$file->getSize(),'storage_path'=>$path,'tags'=>json_encode(collect(explode(',',(string)($data['tags'] ?? '')))->map(fn($tag)=>trim($tag))->filter()->unique()->values()),'usage_rights'=>$data['usage_rights'],'approval_status'=>'pending_review','uploaded_by'=>$request->user()->id,'created_at'=>now(),'updated_at'=>now()]);
        return back()->with('success','Asset uploaded to the private library, awaiting review.');
    }

    public function assetDownload(Request $request, int $asset)
    {
        $org=$this->context($request);
        $record=DB::table('social_assets')->where('organization_id',$org)->where('id',$asset)->first();
        abort_if(! $record,404);
        abort_unless(Storage::disk('local')->exists($record->storage_path),404);
        return Storage::disk('local')->download($record->storage_path,$record->original_name,['Content-Type'=>$record->mime_type,'X-Content-Type-Options'=>'nosniff']);
    }

    public function assignments(Request $request)
    {
        $org=$this->context($request);
        $items=DB::table('social_inbox_items')->where('organization_id',$org)->whereIn('status',['new','in_progress','escalated'])->orderByDesc('received_at')->paginate(25)->withQueryString();
        $posts=DB::table('social_posts')->where('organization_id',$org)->whereIn('status',['draft','pending_approval','approved'])->orderByDesc('created_at')->limit(50)->get();
        $users=$this->members($org);
        return $this->page($request,'Team Assignments',compact('items','posts','users'));
    }

    public function assign(Request $request)
    {
        $org=$this->context($request,'social.assignments.manage');
        $data=$request->validate(['record_type'=>['required',Rule::in(['inbox','post'])],'record_id'=>['required','integer'],'assigned_to_id'=>['required','integer',Rule::exists('users','id')->where('status','active')]]);
        abort_unless(User::whereKey($data['assigned_to_id'])->whereHas('organizations',fn($q)=>$q->where('organizations.id',$org))->exists(),422,'Assignee must be an active organization member.');
        $table=$data['record_type']==='inbox'?'social_inbox_items':'social_posts';
        $record=DB::table($table)->where('organization_id',$org)->where('id',$data['record_id'])->first();abort_if(! $record,404);
        DB::table($table)->where('id',$record->id)->update([$data['record_type']==='inbox'?'assigned_to_id':'owner_id'=>$data['assigned_to_id'],'updated_at'=>now()]);
        return back()->with('success','Social work assigned.');
    }

    public function approvals(Request $request)
    {
        $org=$this->context($request);
        $posts=DB::table('social_posts')->where('organization_id',$org)->where('status','pending_approval')->orderBy('created_at')->paginate(25)->withQueryString();
        return $this->page($request,'Approval Workflows',compact('posts'));
    }

    public function analytics(Request $request)
    {
        $org=$this->context($request,'social.analytics.view');
        $stats=['planned_posts'=>DB::table('social_posts')->where('organization_id',$org)->whereNotNull('planned_for')->count(),'approved_posts'=>DB::table('social_posts')->where('organization_id',$org)->whereIn('status',['approved','scheduled'])->count(),'inbox_items'=>DB::table('social_inbox_items')->where('organization_id',$org)->count(),'responses'=>DB::table('social_inbox_items')->where('organization_id',$org)->whereNotNull('responded_at')->count(),'social_leads'=>DB::table('leads')->where('organization_id',$org)->where('source','social')->count(),'converted_leads'=>DB::table('leads')->where('organization_id',$org)->where('source','social')->whereNotNull('converted_at')->count()];
        $platforms=DB::table('social_inbox_items')->where('organization_id',$org)->select('platform',DB::raw('count(*) as total'))->groupBy('platform')->orderByDesc('total')->get();
        return $this->page($request,'Social Analytics',['stats'=>$stats,'platformActivity'=>$platforms]);
    }

    public function automation(Request $request)
    {
        $org=$this->context($request);
        $rules=DB::table('social_automation_rules')->where('organization_id',$org)->orderBy('name')->get();$users=$this->members($org);
        return $this->page($request,'Automation Rules',compact('rules','users'));
    }

    public function ruleStore(Request $request)
    {
        $org=$this->context($request,'social.rules.manage');
        $data=$request->validate(['name'=>['required','string','max:160'],'trigger_kind'=>['required',Rule::in(['conversation','comment','mention'])],'match_text'=>['nullable','string','max:180'],'assign_to_id'=>['nullable','integer',Rule::exists('users','id')->where('status','active')]]);
        if(!empty($data['assign_to_id'])) abort_unless(User::whereKey($data['assign_to_id'])->whereHas('organizations',fn($q)=>$q->where('organizations.id',$org))->exists(),422,'Assignee must be an active organization member.');
        DB::table('social_automation_rules')->insert(['organization_id'=>$org,...$data,'is_active'=>$request->boolean('is_active'),'created_by'=>$request->user()->id,'created_at'=>now(),'updated_at'=>now()]);
        return back()->with('success','Local social routing rule saved. It does not publish or send platform replies.');
    }

    public function settings(Request $request)
    {
        $org=$this->context($request,'social.settings.manage');
        $settings=DB::table('social_workspace_settings')->where('organization_id',$org)->first();
        $settings=$settings ? json_decode($settings->settings,true) : ['default_platform'=>'other','require_approval'=>true,'reply_template'=>'','webhook_status'=>'not_configured'];
        return $this->page($request,'Social Media Settings',compact('settings'));
    }

    public function settingsUpdate(Request $request)
    {
        $org=$this->context($request,'social.settings.manage');
        $data=$request->validate(['default_platform'=>['required',Rule::in(['facebook','instagram','linkedin','x','tiktok','youtube','other'])],'reply_template'=>['nullable','string','max:3000']]);
        $settings=['default_platform'=>$data['default_platform'],'require_approval'=>$request->boolean('require_approval'),'reply_template'=>$data['reply_template'] ?? '','webhook_status'=>'not_configured'];
        $exists = DB::table('social_workspace_settings')->where('organization_id',$org)->exists();
        DB::table('social_workspace_settings')->updateOrInsert(['organization_id'=>$org],['settings'=>json_encode($settings),'updated_by'=>$request->user()->id,...(! $exists ? ['created_at'=>now()] : []),'updated_at'=>now()]);
        return back()->with('success','Social workspace preferences saved. Provider webhook remains unconfigured.');
    }

    private function members(int $organizationId)
    {
        return User::where('status','active')->whereHas('organizations',fn($q)=>$q->where('organizations.id',$organizationId))->orderBy('name')->get();
    }
}
