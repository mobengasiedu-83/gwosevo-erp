<?php

namespace App\Http\Controllers;

use App\Domain\Administration\AdminAudit;
use App\Domain\Administration\Modules;
use App\Models\Organization;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ProjectsController extends Controller
{
    private function org(Request $request): Organization
    {
        $id = (int) ($request->input('organization_id') ?: $request->query('organization_id') ?: $request->user()->active_organization_id ?: $request->user()->organizations()->orderBy('organizations.name')->value('organizations.id'));
        abort_unless($id && $request->user()->canAccessOrganization($id), 403);
        $org = Organization::findOrFail($id); abort_unless($org->is_active || $request->user()->isSuperAdmin(), 403); return $org;
    }

    private function enabled(): void { abort_unless(app(Modules::class)->enabled('projects'), 404); }
    private function has(Request $request, string $permission): bool { return $request->user()->isSuperAdmin() || $request->user()->can($permission); }
    private function project(Request $request, Organization $org, int $id)
    {
        $project = DB::table('projects')->where('organization_id', $org->id)->where('id', $id)->first(); abort_unless($project, 404);
        $user = $request->user();
        $visible = $this->has($request, 'projects.manage') || (int) $project->owner_id === (int) $user->id
            || DB::table('project_members')->where('project_id', $id)->where('user_id', $user->id)->exists()
            || DB::table('project_tasks')->where('project_id', $id)->where('owner_id', $user->id)->exists();
        abort_unless($visible && ($this->has($request, 'projects.view') || $this->has($request, 'projects.manage')), 403); return $project;
    }

    private function member(Request $request, Organization $org, int $userId): void
    {
        $user = \App\Models\User::whereKey($userId)->where('status', 'active')->firstOrFail(); abort_unless($user->canAccessOrganization($org->id), 422, 'Choose an active user in this organization.');
    }

    public function index(Request $request)
    {
        $this->enabled(); abort_unless($this->has($request, 'projects.view') || $this->has($request, 'projects.manage') || $this->has($request, 'projects.tasks.manage'), 403); $org = $this->org($request); $user = $request->user();
        $query = DB::table('projects as p')->join('users as u', 'u.id', '=', 'p.owner_id')->where('p.organization_id', $org->id);
        if (! $this->has($request, 'projects.manage')) $query->where(function ($q) use ($user) { $q->where('p.owner_id', $user->id)->orWhereExists(fn ($m) => $m->selectRaw('1')->from('project_members as pm')->whereColumn('pm.project_id', 'p.id')->where('pm.user_id', $user->id))->orWhereExists(fn ($t) => $t->selectRaw('1')->from('project_tasks as pt')->whereColumn('pt.project_id', 'p.id')->where('pt.owner_id', $user->id)); });
        $projects = $query->select('p.*', 'u.name as owner_name')->orderByDesc('p.updated_at')->get()->map(function ($p) {
            $p->task_count = DB::table('project_tasks')->where('project_id', $p->id)->whereNull('parent_task_id')->count();
            $p->done_count = DB::table('project_tasks')->where('project_id', $p->id)->whereNull('parent_task_id')->where('status', 'done')->count();
            $p->overdue_count = DB::table('project_tasks')->where('project_id', $p->id)->whereNotIn('status', ['done','cancelled'])->whereDate('due_on', '<', today())->count(); return $p;
        });
        $users = $org->users()->where('users.status', 'active')->orderBy('users.name')->get(['users.id','users.name']);
        $reports = $this->has($request, 'projects.reports.view') ? [
            'overdue' => DB::table('project_tasks')->where('organization_id',$org->id)->whereNotIn('status',['done','cancelled'])->whereDate('due_on','<',today())->count(),
            'hours' => (float) DB::table('project_time_entries')->where('organization_id',$org->id)->sum('hours'),
            'active' => DB::table('projects')->where('organization_id',$org->id)->where('status','active')->count(),
            'workload' => DB::table('project_tasks as t')->join('users as u','u.id','=','t.owner_id')->where('t.organization_id',$org->id)->whereNotIn('t.status',['done','cancelled'])->groupBy('u.id','u.name')->orderBy('u.name')->select('u.name',DB::raw('count(*) as task_count'))->get(),
        ] : null;
        return view('projects.index', compact('org','projects','users','reports'));
    }

    public function show(Request $request, int $project)
    {
        $this->enabled(); $org = $this->org($request); $p = $this->project($request,$org,$project);
        $members = DB::table('project_members as m')->join('users as u','u.id','=','m.user_id')->where('m.project_id',$p->id)->select('m.*','u.name','u.email')->orderBy('u.name')->get();
        $workstreams = DB::table('project_workstreams')->where('project_id',$p->id)->orderBy('name')->get();
        $tasks = DB::table('project_tasks as t')->leftJoin('users as u','u.id','=','t.owner_id')->where('t.project_id',$p->id)->select('t.*','u.name as owner_name')->orderByRaw('t.parent_task_id is null desc')->orderBy('t.due_on')->get();
        $tasks->each(function($task){ $task->hours=(float)DB::table('project_time_entries')->where('task_id',$task->id)->sum('hours'); $task->dependencies=DB::table('project_task_dependencies as d')->join('project_tasks as t','t.id','=','d.depends_on_task_id')->where('d.task_id',$task->id)->select('t.id','t.title','t.status')->get(); $task->comments=DB::table('project_comments as c')->join('users as u','u.id','=','c.user_id')->where('c.task_id',$task->id)->select('c.*','u.name as user_name')->orderBy('c.created_at')->get()->each(function($c){$c->body=Crypt::decryptString($c->body);}); $task->attachments=DB::table('project_attachments')->where('task_id',$task->id)->orderByDesc('created_at')->get(); });
        $milestones=DB::table('project_milestones')->where('project_id',$p->id)->orderBy('due_on')->get(); $risks=DB::table('project_risks')->where('project_id',$p->id)->orderByDesc('created_at')->get();
        $users=$org->users()->where('users.status','active')->orderBy('users.name')->get(['users.id','users.name']);
        $timeEntries=DB::table('project_time_entries as t')->join('users as u','u.id','=','t.user_id')->where('t.project_id',$p->id)->orderByDesc('t.work_date')->limit(30)->select('t.*','u.name as user_name')->get();
        return view('projects.show',compact('org','p','members','workstreams','tasks','milestones','risks','users','timeEntries'));
    }

    public function store(Request $request)
    {
        $this->enabled(); abort_unless($this->has($request,'projects.manage'),403); $org=$this->org($request); $d=$request->validate(['name'=>['required','string','max:180'],'description'=>['nullable','string','max:10000'],'client_name'=>['nullable','string','max:180'],'budget'=>['nullable','numeric','min:0'],'starts_on'=>['nullable','date'],'ends_on'=>['nullable','date','after_or_equal:starts_on'],'status'=>['required',Rule::in(['active','on_hold','completed','cancelled'])],'owner_id'=>['required','integer']]);
        $this->member($request,$org,$d['owner_id']); $id=DB::table('projects')->insertGetId([...$d,'organization_id'=>$org->id,'created_by'=>$request->user()->id,'created_at'=>now(),'updated_at'=>now()]); DB::table('project_members')->insert(['project_id'=>$id,'user_id'=>$d['owner_id'],'member_role'=>'owner','created_at'=>now(),'updated_at'=>now()]); AdminAudit::record('projects.created','projects',$id,null,$d,$org->id); return redirect()->route('projects.show',$id)->with('success','Project created.');
    }

    public function memberStore(Request $request,int $project){$this->enabled();$org=$this->org($request);$p=$this->project($request,$org,$project);abort_unless($this->has($request,'projects.manage'),403);$d=$request->validate(['user_id'=>['required','integer'],'member_role'=>['nullable','string','max:80']]);$this->member($request,$org,$d['user_id']);DB::table('project_members')->updateOrInsert(['project_id'=>$p->id,'user_id'=>$d['user_id']],['member_role'=>$d['member_role']??'member','updated_at'=>now(),'created_at'=>now()]);return back()->with('success','Project member saved.');}

    public function workstreamStore(Request $request,int $project){$this->enabled();$org=$this->org($request);$p=$this->project($request,$org,$project);abort_unless($this->has($request,'projects.manage'),403);$d=$request->validate(['name'=>['required','string','max:160'],'department_name'=>['nullable','string','max:120'],'lead_user_id'=>['nullable','integer']]);if($d['lead_user_id']??null)$this->member($request,$org,$d['lead_user_id']);DB::table('project_workstreams')->insert([...$d,'organization_id'=>$org->id,'project_id'=>$p->id,'created_at'=>now(),'updated_at'=>now()]);return back()->with('success','Workstream added.');}

    public function taskStore(Request $request,int $project){$this->enabled();$org=$this->org($request);$p=$this->project($request,$org,$project);abort_unless($this->has($request,'projects.tasks.manage')||$this->has($request,'projects.manage'),403);$d=$request->validate(['title'=>['required','string','max:200'],'description'=>['nullable','string','max:10000'],'workstream_id'=>['nullable','integer'],'parent_task_id'=>['nullable','integer'],'owner_id'=>['nullable','integer'],'priority'=>['required',Rule::in(['low','medium','high','urgent'])],'status'=>['required',Rule::in(['backlog','todo','in_progress','blocked','pending_approval','done','cancelled'])],'starts_on'=>['nullable','date'],'due_on'=>['nullable','date'],'estimate_hours'=>['nullable','numeric','min:0','max:100000'],'is_deliverable'=>['nullable','boolean']]);
        if($d['workstream_id']??null)abort_unless(DB::table('project_workstreams')->where('project_id',$p->id)->where('id',$d['workstream_id'])->exists(),422);if($d['parent_task_id']??null)abort_unless(DB::table('project_tasks')->where('project_id',$p->id)->where('id',$d['parent_task_id'])->exists(),422);if($d['owner_id']??null){$this->member($request,$org,$d['owner_id']);abort_unless($this->has($request,'projects.tasks.assign')||$d['owner_id']==$request->user()->id||$this->has($request,'projects.manage'),403);}
        $id=DB::table('project_tasks')->insertGetId([...$d,'organization_id'=>$org->id,'project_id'=>$p->id,'created_by'=>$request->user()->id,'is_deliverable'=>(bool)($d['is_deliverable']??false),'approval_status'=>($d['is_deliverable']??false)?'pending':'not_required','created_at'=>now(),'updated_at'=>now()]);AdminAudit::record('projects.task.created','project_tasks',$id,null,$d,$org->id);return back()->with('success','Task added to the project.');}

    public function taskUpdate(Request $request,int $task){$this->enabled();$org=$this->org($request);$t=DB::table('project_tasks')->where('organization_id',$org->id)->where('id',$task)->first();abort_unless($t,404);$p=$this->project($request,$org,$t->project_id);$self=(int)$t->owner_id===(int)$request->user()->id;abort_unless($self||$this->has($request,'projects.tasks.manage')||$this->has($request,'projects.manage'),403);$d=$request->validate(['status'=>['required',Rule::in(['backlog','todo','in_progress','blocked','pending_approval','done','cancelled'])],'priority'=>['nullable',Rule::in(['low','medium','high','urgent'])],'due_on'=>['nullable','date']]);if($d['status']==='done'&&$t->is_deliverable&&$t->approval_status!=='approved')abort(422,'A deliverable must be approved before it can be completed.');DB::table('project_tasks')->where('id',$task)->update([...$d,'updated_at'=>now()]);AdminAudit::record('projects.task.updated','project_tasks',$task,['status'=>$t->status],$d,$org->id);return back()->with('success','Task updated.');}

    public function dependencyStore(Request $request,int $task){$this->enabled();$org=$this->org($request);$t=DB::table('project_tasks')->where('organization_id',$org->id)->where('id',$task)->firstOrFail();$p=$this->project($request,$org,$t->project_id);abort_unless($this->has($request,'projects.tasks.manage')||$this->has($request,'projects.manage'),403);$d=$request->validate(['depends_on_task_id'=>['required','integer','different:task']]);$dep=DB::table('project_tasks')->where('organization_id',$org->id)->where('project_id',$p->id)->where('id',$d['depends_on_task_id'])->firstOrFail();abort_if($dep->id===$t->id,422);$seen=[$t->id];$cursor=$dep->id;while($cursor&&!in_array($cursor,$seen,true)){$seen[]=$cursor;$cursor=DB::table('project_task_dependencies')->where('task_id',$cursor)->value('depends_on_task_id');}abort_if($cursor===$t->id,422,'This dependency would create a cycle.');DB::table('project_task_dependencies')->insertOrIgnore(['task_id'=>$t->id,'depends_on_task_id'=>$dep->id,'created_at'=>now(),'updated_at'=>now()]);return back()->with('success','Task dependency saved.');}

    public function milestoneStore(Request $request,int $project){$this->enabled();$org=$this->org($request);$p=$this->project($request,$org,$project);abort_unless($this->has($request,'projects.manage'),403);$d=$request->validate(['title'=>['required','string','max:180'],'description'=>['nullable','string','max:10000'],'due_on'=>['nullable','date'],'workstream_id'=>['nullable','integer'],'requires_approval'=>['nullable','boolean']]);if($d['workstream_id']??null)abort_unless(DB::table('project_workstreams')->where('project_id',$p->id)->where('id',$d['workstream_id'])->exists(),422);DB::table('project_milestones')->insert([...$d,'organization_id'=>$org->id,'project_id'=>$p->id,'status'=>($d['requires_approval']??false)?'pending_approval':'upcoming','created_at'=>now(),'updated_at'=>now()]);return back()->with('success','Milestone created.');}

    public function milestoneApprove(Request $request,int $milestone){$this->enabled();$org=$this->org($request);$m=DB::table('project_milestones')->where('organization_id',$org->id)->where('id',$milestone)->firstOrFail();$this->project($request,$org,$m->project_id);abort_unless($this->has($request,'projects.approve'),403);abort_unless($m->status==='pending_approval',422);DB::table('project_milestones')->where('id',$milestone)->update(['status'=>'approved','approved_by'=>$request->user()->id,'approved_at'=>now(),'updated_at'=>now()]);return back()->with('success','Milestone approved.');}

    public function taskApprove(Request $request,int $task){$this->enabled();$org=$this->org($request);$t=DB::table('project_tasks')->where('organization_id',$org->id)->where('id',$task)->firstOrFail();$this->project($request,$org,$t->project_id);abort_unless($this->has($request,'projects.approve'),403);abort_unless($t->is_deliverable&&$t->approval_status==='pending',422);DB::table('project_tasks')->where('id',$task)->update(['approval_status'=>'approved','approved_by'=>$request->user()->id,'approved_at'=>now(),'status'=>'done','updated_at'=>now()]);return back()->with('success','Deliverable approved and marked complete.');}

    public function commentStore(Request $request,int $task){$this->enabled();$org=$this->org($request);$t=DB::table('project_tasks')->where('organization_id',$org->id)->where('id',$task)->firstOrFail();$p=$this->project($request,$org,$t->project_id);abort_unless($this->has($request,'projects.comments'),403);$d=$request->validate(['body'=>['required','string','max:10000'],'mentions'=>['nullable','array','max:20'],'mentions.*'=>['integer']]);foreach($d['mentions']??[] as $uid){$this->member($request,$org,$uid);abort_unless(DB::table('project_members')->where('project_id',$p->id)->where('user_id',$uid)->exists()||(int)$p->owner_id===$uid,422,'Mentions must be project members.');}DB::table('project_comments')->insert(['organization_id'=>$org->id,'project_id'=>$p->id,'task_id'=>$task,'user_id'=>$request->user()->id,'body'=>Crypt::encryptString($d['body']),'mentioned_user_ids'=>json_encode($d['mentions']??[]),'created_at'=>now(),'updated_at'=>now()]);return back()->with('success','Comment posted.');}

    public function timeStore(Request $request,int $task){$this->enabled();$org=$this->org($request);$t=DB::table('project_tasks')->where('organization_id',$org->id)->where('id',$task)->firstOrFail();$p=$this->project($request,$org,$t->project_id);abort_unless((int)$t->owner_id===(int)$request->user()->id||$this->has($request,'projects.time.manage'),403);$d=$request->validate(['work_date'=>['required','date','before_or_equal:today'],'hours'=>['required','numeric','gt:0','lte:24'],'notes'=>['nullable','string','max:2000']]);$id=DB::table('project_time_entries')->insertGetId([...$d,'organization_id'=>$org->id,'project_id'=>$p->id,'task_id'=>$task,'user_id'=>$request->user()->id,'created_at'=>now(),'updated_at'=>now()]);AdminAudit::record('projects.time.logged','project_time_entries',$id,null,['hours'=>$d['hours'],'task_id'=>$task],$org->id);return back()->with('success','Time entry saved.');}

    public function riskStore(Request $request,int $project){$this->enabled();$org=$this->org($request);$p=$this->project($request,$org,$project);abort_unless($this->has($request,'projects.risks.manage'),403);$d=$request->validate(['kind'=>['required',Rule::in(['risk','issue'])],'title'=>['required','string','max:180'],'description'=>['nullable','string','max:10000'],'mitigation'=>['nullable','string','max:10000'],'probability'=>['required',Rule::in(['low','medium','high'])],'impact'=>['required',Rule::in(['low','medium','high'])],'owner_id'=>['nullable','integer'],'due_on'=>['nullable','date']]);if($d['owner_id']??null)$this->member($request,$org,$d['owner_id']);DB::table('project_risks')->insert([...$d,'organization_id'=>$org->id,'project_id'=>$p->id,'created_by'=>$request->user()->id,'created_at'=>now(),'updated_at'=>now()]);return back()->with('success',ucfirst($d['kind']).' recorded.');}

    public function attachmentStore(Request $request,int $task){$this->enabled();$org=$this->org($request);$t=DB::table('project_tasks')->where('organization_id',$org->id)->where('id',$task)->firstOrFail();$p=$this->project($request,$org,$t->project_id);abort_unless($this->has($request,'projects.attachments'),403);$d=$request->validate(['file'=>['required','file','max:10240','mimes:pdf,txt,csv,png,jpg,jpeg,doc,docx,xls,xlsx']]);$file=$d['file'];$path='projects/'.$org->id.'/'.$p->id.'/'.$task.'/'.\Illuminate\Support\Str::uuid().'.enc';Storage::disk('local')->put($path,Crypt::encrypt(file_get_contents($file->getRealPath())));DB::table('project_attachments')->insert(['organization_id'=>$org->id,'project_id'=>$p->id,'task_id'=>$task,'uploaded_by'=>$request->user()->id,'original_name'=>$file->getClientOriginalName(),'mime_type'=>$file->getMimeType()?:'application/octet-stream','size_bytes'=>$file->getSize(),'storage_path'=>$path,'created_at'=>now(),'updated_at'=>now()]);return back()->with('success','Encrypted attachment uploaded.');}

    public function attachmentDownload(Request $request,int $attachment){$this->enabled();$org=$this->org($request);$a=DB::table('project_attachments')->where('organization_id',$org->id)->where('id',$attachment)->firstOrFail();$this->project($request,$org,$a->project_id);abort_unless($this->has($request,'projects.attachments'),403);$contents=Crypt::decrypt(Storage::disk('local')->get($a->storage_path));AdminAudit::record('projects.attachment.downloaded','project_attachments',$a->id,null,['name'=>$a->original_name],$org->id);return response($contents,200,['Content-Type'=>$a->mime_type,'Content-Disposition'=>'attachment; filename="'.str_replace('"','',$a->original_name).'"','Content-Length'=>(string)strlen($contents)]);}
}
