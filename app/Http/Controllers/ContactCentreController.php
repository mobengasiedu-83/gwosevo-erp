<?php

namespace App\Http\Controllers;

use App\Domain\ContactCentre\VitalPbxCallHistory;
use App\Domain\ContactCentre\VitalPbxDialer;
use App\Domain\ContactCentre\VitalPbxExtensions;
use App\Models\Account;
use App\Models\CallEvaluation;
use App\Models\CallEvaluationForm;
use App\Models\CallRecord;
use App\Models\Contact;
use App\Models\PbxConnection;
use App\Models\PbxExtension;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ContactCentreController extends Controller
{
    private function enabled(): void { abort_unless(app(\App\Domain\Administration\Modules::class)->enabled('contact_centre'), 404); }
    private function org(Request $request): int { $id=(int)$request->user()->active_organization_id; abort_unless($id && $request->user()->canAccessOrganization($id), 403); return $id; }

    public function index(Request $request)
    {
        $this->enabled(); abort_unless($request->user()->can('calls.view'), 403); $org=$this->org($request);
        $calls=CallRecord::where('organization_id',$org)->with(['agent','contact','account'])->when($request->filled('queue'),fn($q)=>$q->where('queue',$request->queue))->when($request->filled('status'),fn($q)=>$q->where('status',$request->status))->latest('started_at')->paginate(25)->withQueryString();
        $today=CallRecord::where('organization_id',$org)->whereDate('started_at',today());
        $stats=['total'=>(clone $today)->count(),'answered'=>(clone $today)->where('status','answered')->count(),'abandoned'=>(clone $today)->where('status','abandoned')->count(),'avg_wait'=>(int)((clone $today)->avg('wait_seconds')??0)];
        $queues=CallRecord::where('organization_id',$org)->whereNotNull('queue')->distinct()->orderBy('queue')->pluck('queue');
        $agents=User::where('status','active')->whereHas('organizations',fn($q)=>$q->whereKey($org))->with(['pbxExtensions'=>fn($q)=>$q->whereHas('connection',fn($c)=>$c->where('organization_id',$org))])->orderBy('name')->get();
        $connections=PbxConnection::where('organization_id',$org)->with('extensions.user')->latest()->get();
        $dialMappings=PbxExtension::with('connection')->where('user_id',$request->user()->id)->whereHas('connection',fn($q)=>$q->where('organization_id',$org)->where('driver','vitalpbx'))->orderBy('extension')->get();
        $forms=CallEvaluationForm::where('organization_id',$org)->where('is_active',true)->get();
        return view('contact-centre.index',compact('calls','stats','queues','agents','connections','forms','dialMappings'));
    }

    public function dial(Request $request, VitalPbxDialer $dialer)
    {
        $this->enabled();
        abort_unless($request->user()->can('calls.dial'), 403);
        $org = $this->org($request);
        $data = $request->validate([
            'extension_id' => ['required', 'integer'],
            'destination' => ['required', 'regex:/^[0-9*#+]{2,32}$/'],
        ], ['destination.regex' => 'Enter a phone number or extension using digits, *, #, or + (2–32 characters).']);

        $mapping = PbxExtension::with('connection')->whereKey($data['extension_id'])
            ->where('user_id', $request->user()->id)
            ->whereHas('connection', fn ($query) => $query->where('organization_id', $org)->where('driver', 'vitalpbx'))
            ->firstOrFail();

        try {
            $result = $dialer->dial($mapping->connection, $mapping->extension, $data['destination']);
        } catch (\RuntimeException $exception) {
            return back()->withErrors(['dial' => $exception->getMessage()])->withInput();
        }

        $callId = data_get($result, 'variables.callId') ?: data_get($result, 'callId');
        return back()->with('success', 'Call request sent to VitalPBX. Your extension '.$mapping->extension.' should ring first.'.($callId ? ' Call ID: '.$callId : ''));
    }

    public function storeCall(Request $request)
    {
        $this->enabled(); abort_unless($request->user()->can('calls.disposition'),403); $org=$this->org($request);
        $data=$request->validate(['direction'=>['required',Rule::in(['inbound','outbound'])],'status'=>['required',Rule::in(['answered','missed','abandoned'])],'from_number'=>['nullable','string','max:40'],'to_number'=>['nullable','string','max:40'],'queue'=>['nullable','string','max:100'],'started_at'=>['required','date'],'wait_seconds'=>['nullable','integer','min:0','max:86400'],'duration_seconds'=>['nullable','integer','min:0','max:86400'],'disposition'=>['nullable','string','max:60'],'notes'=>['nullable','string','max:5000'],'follow_up_at'=>['nullable','date'],'follow_up_owner_id'=>['nullable','exists:users,id'],'recording_reference'=>['nullable','string','max:255'],'recording_expires_at'=>['nullable','date']]);
        if (!empty($data['follow_up_owner_id'])) abort_unless(User::whereKey($data['follow_up_owner_id'])->whereHas('organizations',fn($q)=>$q->whereKey($org))->exists(),422,'Follow-up owner must belong to the active organization.');
        $number=$data['direction']==='inbound'?($data['from_number']??null):($data['to_number']??null); $contact=null; $account=null;
        if($number){$digits=preg_replace('/\D+/','',$number);$contact=Contact::where('organization_id',$org)->get()->first(fn($candidate)=>$digits!==''&&preg_replace('/\D+/','',$candidate->phone??'')===$digits);if($contact)$account=Account::where('organization_id',$org)->find($contact->account_id);else $account=Account::where('organization_id',$org)->get()->first(fn($candidate)=>$digits!==''&&preg_replace('/\D+/','',$candidate->phone??'')===$digits);}
        CallRecord::create([...$data,'organization_id'=>$org,'contact_id'=>$contact?->id,'account_id'=>$account?->id,'agent_id'=>$request->user()->id,'created_by'=>$request->user()->id,'ended_at'=>now()]);
        return back()->with('success',$contact||$account?'Call logged and matched to a customer record.':'Call logged. No customer match was found. Verify identity before linking customer records.');
    }

    public function disposition(Request $request, CallRecord $call)
    {
        $this->enabled(); abort_unless($request->user()->can('calls.disposition'),403); abort_unless((int)$call->organization_id===$this->org($request),404);
        $data=$request->validate(['disposition'=>['required','string','max:60'],'notes'=>['nullable','string','max:5000'],'follow_up_at'=>['nullable','date'],'follow_up_owner_id'=>['nullable','exists:users,id']]);if(!empty($data['follow_up_owner_id']))abort_unless(User::whereKey($data['follow_up_owner_id'])->whereHas('organizations',fn($q)=>$q->whereKey($call->organization_id))->exists(),422,'Follow-up owner must belong to the active organization.');$call->update($data);return back()->with('success','Call disposition saved.');
    }

    public function connect(Request $request)
    {
        $this->enabled(); abort_unless($request->user()->can('calls.configure'),403);$org=$this->org($request);
        $data=$request->validate(['name'=>['required','string','max:120'],'base_url'=>['nullable','url','max:255'],'api_token'=>['nullable','string','max:4000'],'is_active'=>['nullable','boolean']]);
        $baseUrl = filled($data['base_url'] ?? null) ? $data['base_url'] : config('vitalpbx.base_url');
        $apiToken = filled($data['api_token'] ?? null) ? $data['api_token'] : config('vitalpbx.api_key');
        abort_if(blank($baseUrl),422,'Set VITALPBX_API_BASE_URL in .env or enter the approved HTTPS API base URL.');
        abort_unless(parse_url($baseUrl, PHP_URL_SCHEME)==='https',422,'PBX API profiles must use HTTPS.');
        $id=Str::random(48);PbxConnection::create(['organization_id'=>$org,'name'=>$data['name'],'driver'=>'vitalpbx','base_url'=>$baseUrl,'api_token'=>$apiToken,'webhook_secret'=>$id,'is_active'=>false,'created_by'=>$request->user()->id]);
        return back()->with('success','Connection profile saved disabled. The webhook signing secret is shown once: '.$id.' Configure an approved VitalPBX/Asterisk interface before activation.');
    }

    public function syncExtensions(Request $request, PbxConnection $connection, VitalPbxExtensions $vitalPbx)
    {
        $this->enabled();
        abort_unless($request->user()->can('calls.configure'), 403);
        abort_unless((int) $connection->organization_id === $this->org($request), 404);
        abort_unless($connection->driver === 'vitalpbx', 422, 'Extension sync is available for VitalPBX profiles.');

        try {
            $extensions = $vitalPbx->fetch($connection);
        } catch (\RuntimeException $exception) {
            return back()->withErrors([$exception->getMessage()]);
        }

        $settings = $connection->settings ?? [];
        $settings['extensions_directory'] = $extensions;
        $settings['extensions_synced_at'] = now()->toIso8601String();
        $connection->update(['settings' => $settings, 'last_connected_at' => now()]);

        return back()->with('success', count($extensions).' VitalPBX extensions synced.');
    }

    public function syncCallHistory(Request $request, PbxConnection $connection, VitalPbxCallHistory $callHistory)
    {
        $this->enabled();
        abort_unless($request->user()->can('calls.configure'), 403);
        abort_unless((int) $connection->organization_id === $this->org($request), 404);
        abort_unless($connection->driver === 'vitalpbx', 422, 'Call history sync is available for VitalPBX profiles.');

        $data = $request->validate(['lookback_days' => ['nullable', 'integer', 'min:1', 'max:365']]);

        try {
            $result = $callHistory->sync($connection, isset($data['lookback_days']) ? (int) $data['lookback_days'] : null);
        } catch (\RuntimeException $exception) {
            return back()->withErrors([$exception->getMessage()]);
        }

        return back()->with('success', sprintf(
            'VitalPBX call history synced: %d new, %d updated, %d skipped.',
            $result['imported'],
            $result['updated'],
            $result['skipped']
        ));
    }

    public function mapAgent(Request $request)
    {
        $this->enabled();abort_unless($request->user()->can('calls.configure'),403);$org=$this->org($request);
        $data=$request->validate(['connection_id'=>['required','exists:pbx_connections,id'],'user_id'=>['required','exists:users,id'],'extension'=>['required','string','max:32'],'queue'=>['nullable','string','max:100']]);
        abort_unless(PbxConnection::where('organization_id',$org)->whereKey($data['connection_id'])->exists() && User::whereHas('organizations',fn($q)=>$q->whereKey($org))->whereKey($data['user_id'])->exists(),404);
        PbxExtension::updateOrCreate(['connection_id'=>$data['connection_id'],'user_id'=>$data['user_id']],['extension'=>$data['extension'],'queue'=>$data['queue']??null]);return back()->with('success','Agent extension mapping saved.');
    }

    public function formStore(Request $request)
    {
        $this->enabled();abort_unless($request->user()->can('calls.qa.manage'),403);$org=$this->org($request);
        $d=$request->validate(['name'=>['required','string','max:120'],'criteria'=>['required','string','max:2000']]);$criteria=array_values(array_filter(array_map('trim',preg_split('/\r?\n/',$d['criteria']))));abort_if(!$criteria||count($criteria)>20,422,'Enter between 1 and 20 evaluation criteria.');
        CallEvaluationForm::create(['organization_id'=>$org,'name'=>$d['name'],'criteria'=>$criteria,'created_by'=>$request->user()->id]);return back()->with('success','Quality evaluation form created.');
    }

    public function evaluate(Request $request, CallRecord $call)
    {
        $this->enabled();abort_unless($request->user()->can('calls.qa.manage'),403);abort_unless((int)$call->organization_id===$this->org($request),404);
        $form=CallEvaluationForm::where('organization_id',$call->organization_id)->where('is_active',true)->findOrFail($request->integer('form_id'));$scores=$request->validate(['scores'=>['required','array'],'scores.*'=>['required','integer','min:0','max:5'],'notes'=>['nullable','string','max:5000']]);
        abort_unless(count($scores['scores'])===count($form->criteria),422);$percent=(int)round(array_sum($scores['scores'])/(5*count($form->criteria))*100);
        CallEvaluation::create(['call_record_id'=>$call->id,'form_id'=>$form->id,'evaluator_id'=>$request->user()->id,'scores'=>$scores['scores'],'score_percent'=>$percent,'notes'=>$scores['notes']??null]);return back()->with('success',"Quality review saved ({$percent}%).");
    }

    public function webhook(Request $request, PbxConnection $connection)
    {
        abort_unless($connection->is_active,404);
        $timestamp=(int)$request->header('X-PBX-Timestamp',0);$signature=(string)$request->header('X-PBX-Signature','');
        abort_unless($timestamp && abs(now()->timestamp-$timestamp)<=300 && hash_equals(hash_hmac('sha256',$timestamp.'.'.$request->getContent(),$connection->webhook_secret),$signature),401);
        $this->enabled();
        $event=$request->validate(['event'=>['required',Rule::in(['call.completed','agent.activity'])],'id'=>['required_if:event,call.completed','nullable','string','max:150'],'direction'=>['required_if:event,call.completed',Rule::in(['inbound','outbound'])],'status'=>['required_if:event,call.completed',Rule::in(['answered','missed','abandoned'])],'from'=>['nullable','string','max:40'],'to'=>['nullable','string','max:40'],'queue'=>['nullable','string','max:100'],'extension'=>['required_if:event,agent.activity','nullable','string','max:32'],'availability'=>['nullable',Rule::in(['available','busy','away','offline','unknown'])],'started_at'=>['nullable','date'],'answered_at'=>['nullable','date'],'ended_at'=>['nullable','date'],'wait_seconds'=>['nullable','integer','min:0'],'duration_seconds'=>['nullable','integer','min:0'],'recording_reference'=>['nullable','string','max:255']]);
        if($event['event']==='agent.activity'){$ext=PbxExtension::where('connection_id',$connection->id)->where('extension',$event['extension']??'')->first();if($ext){$ext->update(['availability'=>$event['availability']??'unknown','activity_at'=>now()]);}return response()->json(['accepted'=>true]);}
        $number=($event['direction']==='inbound'?($event['from']??null):($event['to']??null));$digits=preg_replace('/\D+/','',$number??'');$org=(int)$connection->organization_id;
        $contact=$digits?Contact::where('organization_id',$org)->get()->first(fn($c)=>preg_replace('/\D+/','',$c->phone??'')===$digits):null;
        $account=$contact?Account::where('organization_id',$org)->find($contact->account_id):($digits?Account::where('organization_id',$org)->get()->first(fn($a)=>preg_replace('/\D+/','',$a->phone??'')===$digits):null);
        $mapping=PbxExtension::where('connection_id',$connection->id)->where('extension',$event['extension']??'')->first();
        $record=CallRecord::updateOrCreate(['connection_id'=>$connection->id,'external_id'=>$event['id']??null],['organization_id'=>$org,'direction'=>$event['direction'],'status'=>$event['status'],'from_number'=>$event['from']??null,'to_number'=>$event['to']??null,'queue'=>$event['queue']??null,'agent_id'=>$mapping?->user_id,'contact_id'=>$contact?->id,'account_id'=>$account?->id,'started_at'=>$event['started_at']??now(),'answered_at'=>$event['answered_at']??null,'ended_at'=>$event['ended_at']??now(),'wait_seconds'=>$event['wait_seconds']??0,'duration_seconds'=>$event['duration_seconds']??0,'recording_reference'=>$event['recording_reference']??null]);
        return response()->json(['accepted'=>true,'call_id'=>$record->id]);
    }

    public function screenPop(Request $request)
    {
        $this->enabled();abort_unless($request->user()->can('calls.view'),403);$org=$this->org($request);$data=$request->validate(['number'=>['required','string','max:40']]);$digits=preg_replace('/\D+/','',$data['number']);
        $contact=$digits?Contact::where('organization_id',$org)->get()->first(fn($c)=>preg_replace('/\D+/','',$c->phone??'')===$digits):null;
        $account=$contact?Account::where('organization_id',$org)->find($contact->account_id):($digits?Account::where('organization_id',$org)->get()->first(fn($a)=>preg_replace('/\D+/','',$a->phone??'')===$digits):null);
        return response()->json(['contact'=>$contact?->only(['id','first_name','last_name','phone','email']),'account'=>$account?->only(['id','name','phone','email'])]);
    }

    public function activateConnection(Request $request, PbxConnection $connection)
    {
        $this->enabled();abort_unless($request->user()->can('calls.configure'),403);abort_unless((int)$connection->organization_id===$this->org($request),404);
        abort_if(!$connection->base_url,422,'Add the approved provider API base URL before activating this profile.');
        $connection->update(['is_active'=>true]);return back()->with('success','PBX profile activated for signed inbound events.');
    }
}
