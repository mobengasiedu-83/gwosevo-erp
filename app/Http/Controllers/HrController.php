<?php

namespace App\Http\Controllers;

use App\Domain\Administration\AdminAudit;
use App\Domain\Administration\Modules;
use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Branch;
use App\Models\HrDisciplinaryCase;
use App\Models\HrEmployee;
use App\Models\HrEmployeeDocument;
use App\Models\HrEmploymentContract;
use App\Models\HrJobHistory;
use App\Models\HrLifecycleTask;
use App\Models\HrTrainingCourse;
use App\Models\HrTrainingRecord;
use App\Models\Organization;
use App\Models\Position;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class HrController extends Controller
{
    private function enabled(): void { abort_unless(app(Modules::class)->enabled('hr'), 404); }
    private function organization(Request $request): Organization
    {
        $id = (int) ($request->input('organization_id') ?: $request->query('organization_id') ?: $request->user()->active_organization_id ?: $request->user()->organizations()->orderBy('organizations.name')->value('organizations.id'));
        abort_unless($id && $request->user()->canAccessOrganization($id), 403);
        $organization=Organization::findOrFail($id);
        abort_unless($organization->is_active || $request->user()->isSuperAdmin(),403);
        return $organization;
    }
    private function employee(Request $request, HrEmployee $employee): HrEmployee
    {
        abort_unless($employee->organization_id === $this->organization($request)->id, 404);
        return $employee;
    }
    private function directoryOptions(int $org): array
    {
        return [
            'departments' => Department::where('organization_id', $org)->where('is_active', true)->orderBy('name')->get(),
            'teams' => Team::where('organization_id', $org)->where('is_active', true)->orderBy('name')->get(),
            'branches' => Branch::where('organization_id', $org)->where('is_active', true)->orderBy('name')->get(),
            'positions' => Position::where('organization_id', $org)->where('is_active', true)->orderBy('title')->get(),
            'managers' => HrEmployee::where('organization_id', $org)->where('status', 'active')->orderBy('first_name')->orderBy('last_name')->get(),
            'users' => User::where('status', 'active')->whereHas('organizations', fn ($q) => $q->where('organizations.id', $org))->orderBy('name')->get(),
        ];
    }

    public function index(Request $request)
    {
        $this->enabled(); abort_unless($request->user()->can('hr.view'), 403);
        $org = $this->organization($request);
        $employees = HrEmployee::where('organization_id', $org->id)->with(['department','team','position','manager'])->when($request->filled('search'), fn ($q) => $q->where(fn ($q) => $q->where('employee_number','like','%'.$request->search.'%')->orWhere('first_name','like','%'.$request->search.'%')->orWhere('last_name','like','%'.$request->search.'%')->orWhere('work_email','like','%'.$request->search.'%')))->when($request->filled('status'), fn ($q) => $q->where('status',$request->status))->orderBy('last_name')->paginate(20)->withQueryString();
        $organizations = $request->user()->isSuperAdmin() ? Organization::where('is_active', true)->orderBy('name')->get() : $request->user()->organizations()->where('organizations.is_active',true)->orderBy('organizations.name')->get();
        $stats = ['total' => HrEmployee::where('organization_id',$org->id)->count(), 'active' => HrEmployee::where('organization_id',$org->id)->where('status','active')->count(), 'on_leave' => HrEmployee::where('organization_id',$org->id)->where('status','on_leave')->count(), 'separations' => HrEmployee::where('organization_id',$org->id)->where('status','terminated')->where('end_date','>=',today()->subYear())->count()];
        $expiring = HrEmploymentContract::with('employee')->where('organization_id',$org->id)->whereNotNull('renewal_date')->whereBetween('renewal_date',[today(),today()->addDays(90)])->orderBy('renewal_date')->limit(15)->get();
        $options = $this->directoryOptions($org->id);
        $courses = HrTrainingCourse::where('organization_id',$org->id)->where('is_active',true)->orderBy('name')->get();
        $reports = $request->user()->can('hr.reports.view') ? HrEmployee::where('organization_id',$org->id)->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total','status') : null;
        $averageTenureYears = $reports ? round((HrEmployee::where('organization_id',$org->id)->whereNotNull('hire_date')->get(['hire_date','end_date'])->avg(fn($e)=>$e->hire_date->diffInDays($e->end_date ?: today())) ?: 0) / 365.25,1) : null;
        $turnoverPercent = $reports ? round($stats['separations'] / max($stats['total'],1) * 100,1) : null;
        return view('hr.index', compact('org','organizations','employees','stats','expiring','options','courses','reports','averageTenureYears','turnoverPercent'));
    }

    public function store(Request $request)
    {
        $this->enabled(); abort_unless($request->user()->can('hr.manage'),403); $org=$this->organization($request);
        $data=$request->validate(['employee_number'=>['required','string','max:50',Rule::unique('hr_employees')->where('organization_id',$org->id)],'first_name'=>['required','string','max:100'],'last_name'=>['required','string','max:100'],'work_email'=>['nullable','email','max:255'],'work_phone'=>['nullable','string','max:50'],'user_id'=>['nullable','integer'],'employment_type'=>['required',Rule::in(['permanent','fixed_term','contractor','intern','temporary'])],'status'=>['required',Rule::in(['preboarding','active','on_leave','terminated'])],'hire_date'=>['nullable','date'],'end_date'=>['nullable','date','after_or_equal:hire_date'],'department_id'=>['nullable','integer'],'team_id'=>['nullable','integer'],'branch_id'=>['nullable','integer'],'position_id'=>['nullable','integer'],'manager_employee_id'=>['nullable','integer'],'home_address'=>['nullable','string','max:2000'],'date_of_birth'=>['nullable','date','before:today'],'emergency_contact_name'=>['nullable','string','max:150'],'emergency_contact_phone'=>['nullable','string','max:50']]);
        $this->validateRelations($data,$org->id);
        abort_unless($request->user()->can('hr.sensitive.manage') || collect(['home_address','date_of_birth','emergency_contact_name','emergency_contact_phone'])->every(fn($k)=>empty($data[$k])),403,'Sensitive personal information requires HR sensitive-data access.');
        $personal=array_intersect_key($data,array_flip(['home_address','date_of_birth','emergency_contact_name','emergency_contact_phone']));
        $employee=HrEmployee::create([...collect($data)->except(array_keys($personal))->all(),'personal_data'=>$personal ?: null,'organization_id'=>$org->id,'created_by'=>$request->user()->id]);
        if ($employee->status==='terminated') $employee->update(['end_date'=>$employee->end_date ?: today()]);
        AdminAudit::record('hr.employee.created',HrEmployee::class,$employee->id,null,['employee_number'=>$employee->employee_number,'status'=>$employee->status],$org->id);
        return to_route('hr.employees.show',['employee'=>$employee,'organization_id'=>$org->id])->with('success','Employee record created.');
    }

    public function update(Request $request, HrEmployee $employee)
    {
        $this->enabled(); abort_unless($request->user()->can('hr.manage'),403); $employee=$this->employee($request,$employee); $org=$employee->organization_id;
        $data=$request->validate(['employee_number'=>['required','string','max:50',Rule::unique('hr_employees')->where('organization_id',$org)->ignore($employee->id)],'first_name'=>['required','string','max:100'],'last_name'=>['required','string','max:100'],'work_email'=>['nullable','email','max:255'],'work_phone'=>['nullable','string','max:50'],'user_id'=>['nullable','integer'],'employment_type'=>['required',Rule::in(['permanent','fixed_term','contractor','intern','temporary'])],'status'=>['required',Rule::in(['preboarding','active','on_leave','terminated'])],'hire_date'=>['nullable','date'],'end_date'=>['nullable','date','after_or_equal:hire_date'],'department_id'=>['nullable','integer'],'team_id'=>['nullable','integer'],'branch_id'=>['nullable','integer'],'position_id'=>['nullable','integer'],'manager_employee_id'=>['nullable','integer'],'home_address'=>['nullable','string','max:2000'],'date_of_birth'=>['nullable','date','before:today'],'emergency_contact_name'=>['nullable','string','max:150'],'emergency_contact_phone'=>['nullable','string','max:50']]);
        $this->validateRelations($data,$org,$employee->id);
        $personal=array_intersect_key($data,array_flip(['home_address','date_of_birth','emergency_contact_name','emergency_contact_phone']));
        if ($personal && ! $request->user()->can('hr.sensitive.manage')) abort(403,'Sensitive personal information requires HR sensitive-data access.');
        $previous=$employee->only(['employee_number','department_id','team_id','branch_id','position_id','manager_employee_id','employment_type','status']);
        $beforeAssignment=array_intersect_key($previous,array_flip(['department_id','team_id','branch_id','position_id','manager_employee_id','employment_type','status']));
        $oldPersonal=$employee->personal_data ?? [];
        $employee->fill([...collect($data)->except(array_keys($personal))->all(),'personal_data'=>$personal ? [...$oldPersonal,...$personal] : $oldPersonal]);
        if ($employee->status==='terminated' && ! $employee->end_date) $employee->end_date=today();
        if ($employee->status!=='terminated' && $employee->end_date && ! isset($data['end_date'])) $employee->end_date=null;
        $employee->save();
        $afterAssignment=$employee->only(array_keys($beforeAssignment));
        if ($beforeAssignment !== $afterAssignment) HrJobHistory::create(['organization_id'=>$org,'employee_id'=>$employee->id,'effective_on'=>today(),'change_type'=>'assignment_or_status','previous_assignment'=>$beforeAssignment,'new_assignment'=>$afterAssignment,'reason'=>'Employee profile update','recorded_by'=>$request->user()->id]);
        AdminAudit::record('hr.employee.updated',HrEmployee::class,$employee->id,$previous,$employee->only(array_keys($previous)),$org);
        return back()->with('success','Employee record updated. Changes to assignment and employment status were recorded in job history.');
    }

    private function validateRelations(array $data,int $org,?int $employeeId=null): void
    {
        if (! empty($data['user_id'])) abort_unless(User::where('status','active')->whereHas('organizations',fn($q)=>$q->where('organizations.id',$org))->whereKey($data['user_id'])->exists(),422,'Linked account must be an active member of this organization.');
        foreach (['department_id'=>Department::class,'team_id'=>Team::class,'branch_id'=>Branch::class,'position_id'=>Position::class] as $key=>$class) if (! empty($data[$key])) abort_unless($class::where('organization_id',$org)->where('is_active',true)->whereKey($data[$key])->exists(),422,'Choose an active organization record for '.$key.'.');
        if (! empty($data['manager_employee_id'])) {
            abort_unless((int)$data['manager_employee_id'] !== $employeeId && HrEmployee::where('organization_id',$org)->where('status','active')->whereKey($data['manager_employee_id'])->exists(),422,'Choose an active manager from this organization.');
            $seen=[]; $manager=HrEmployee::where('organization_id',$org)->find($data['manager_employee_id']);
            while ($manager) {
                abort_if($manager->id===$employeeId || isset($seen[$manager->id]),422,'Reporting lines cannot contain a cycle.');
                $seen[$manager->id]=true; $manager=$manager->manager_employee_id ? HrEmployee::where('organization_id',$org)->find($manager->manager_employee_id) : null;
            }
        }
    }

    public function show(Request $request,HrEmployee $employee)
    {
        $this->enabled(); abort_unless($request->user()->can('hr.view'),403); $employee=$this->employee($request,$employee);
        $employee->load(['department','team','branch','position','manager','user','contracts.documents','documents','history.recorder','tasks.assignee','trainingRecords.course','trainingRecords.certificate']);
        $cases=$request->user()->can('hr.cases.view') ? $employee->cases()->latest('opened_on')->get() : collect();
        $options=$this->directoryOptions($employee->organization_id);
        $options['courses']=HrTrainingCourse::where('organization_id',$employee->organization_id)->where('is_active',true)->orderBy('name')->get();
        $options['contracts']=$employee->contracts;
        return view('hr.show',compact('employee','options','cases'));
    }

    public function contract(Request $request,HrEmployee $employee)
    {
        $this->enabled(); abort_unless($request->user()->can('hr.contracts.manage'),403); $employee=$this->employee($request,$employee);
        $data=$request->validate(['reference'=>['required','string','max:80',Rule::unique('hr_employment_contracts')->where('organization_id',$employee->organization_id)],'contract_type'=>['required','string','max:40'],'start_date'=>['nullable','date'],'end_date'=>['nullable','date','after_or_equal:start_date'],'renewal_date'=>['nullable','date'],'status'=>['required',Rule::in(['draft','active','expired','terminated'])],'terms'=>['nullable','string','max:20000']]);
        $contract=$employee->contracts()->create([...$data,'organization_id'=>$employee->organization_id,'created_by'=>$request->user()->id]);
        AdminAudit::record('hr.contract.created',HrEmploymentContract::class,$contract->id,null,['employee_id'=>$employee->id,'reference'=>$contract->reference,'status'=>$contract->status],$employee->organization_id);
        return back()->with('success','Employment contract record saved.');
    }

    public function contractUpdate(Request $request,HrEmploymentContract $contract)
    {
        $this->enabled(); abort_unless($request->user()->can('hr.contracts.manage'),403); abort_unless($contract->organization_id===$this->organization($request)->id,404);
        $data=$request->validate(['reference'=>['required','string','max:80',Rule::unique('hr_employment_contracts')->where('organization_id',$contract->organization_id)->ignore($contract->id)],'contract_type'=>['required','string','max:40'],'start_date'=>['nullable','date'],'end_date'=>['nullable','date','after_or_equal:start_date'],'renewal_date'=>['nullable','date'],'status'=>['required',Rule::in(['draft','active','expired','terminated'])]]);
        if ($request->user()->can('hr.sensitive.manage')) $data['terms']=$request->validate(['terms'=>['nullable','string','max:20000']])['terms'] ?? null;
        $old=$contract->only(array_keys($data)); $contract->update($data);
        AdminAudit::record('hr.contract.updated',HrEmploymentContract::class,$contract->id,$old,$contract->only(array_keys($data)),$contract->organization_id);
        return back()->with('success','Employment contract updated.');
    }

    public function document(Request $request,HrEmployee $employee)
    {
        $this->enabled(); abort_unless($request->user()->can('hr.documents.manage'),403); $employee=$this->employee($request,$employee);
        $data=$request->validate(['category'=>['required',Rule::in(['contract','certificate','identity','policy','other'])],'contract_id'=>['nullable','integer'],'document'=>['required','file','mimes:pdf,doc,docx,xls,xlsx,png,jpg,jpeg','max:10240']]);
        $contract=null; if (! empty($data['contract_id'])) $contract=HrEmploymentContract::where('organization_id',$employee->organization_id)->where('employee_id',$employee->id)->findOrFail($data['contract_id']);
        $file=$data['document']; $path='hr/private/'.Str::ulid().'.enc';
        Storage::disk('local')->put($path,Crypt::encryptString(file_get_contents($file->getRealPath())));
        $doc=$employee->documents()->create(['organization_id'=>$employee->organization_id,'contract_id'=>$contract?->id,'category'=>$data['category'],'original_name'=>basename($file->getClientOriginalName()),'mime_type'=>$file->getMimeType() ?: 'application/octet-stream','size_bytes'=>$file->getSize(),'storage_path'=>$path,'uploaded_by'=>$request->user()->id]);
        AdminAudit::record('hr.document.uploaded',HrEmployeeDocument::class,$doc->id,null,['employee_id'=>$employee->id,'category'=>$doc->category],$employee->organization_id);
        return back()->with('success','Encrypted HR document stored in private application storage.');
    }

    public function download(Request $request,HrEmployeeDocument $document)
    {
        $this->enabled(); abort_unless($request->user()->can('hr.documents.view'),403); abort_unless($document->organization_id===$this->organization($request)->id,404);
        $encrypted=Storage::disk('local')->get($document->storage_path); $contents=Crypt::decryptString($encrypted);
        return response()->streamDownload(fn()=>print($contents),$document->original_name,['Content-Type'=>$document->mime_type,'Content-Length'=>(string)strlen($contents),'X-Content-Type-Options'=>'nosniff','Cache-Control'=>'private, no-store']);
    }

    public function task(Request $request,HrEmployee $employee)
    {
        $this->enabled(); abort_unless($request->user()->can('hr.manage'),403); $employee=$this->employee($request,$employee);
        $data=$request->validate(['phase'=>['required',Rule::in(['onboarding','offboarding'])],'title'=>['required','string','max:180'],'notes'=>['nullable','string','max:3000'],'due_on'=>['nullable','date'],'assigned_to'=>['nullable','integer']]);
        if (! empty($data['assigned_to'])) abort_unless(User::where('status','active')->whereHas('organizations',fn($q)=>$q->where('organizations.id',$employee->organization_id))->whereKey($data['assigned_to'])->exists(),422,'Task assignee must be an active organization member.');
        $task=$employee->tasks()->create([...$data,'organization_id'=>$employee->organization_id,'created_by'=>$request->user()->id]);
        AdminAudit::record('hr.lifecycle_task.created',HrLifecycleTask::class,$task->id,null,['employee_id'=>$employee->id,'phase'=>$task->phase],$employee->organization_id);
        return back()->with('success',ucfirst($task->phase).' checklist item added.');
    }

    public function taskToggle(Request $request,HrLifecycleTask $task)
    {
        $this->enabled(); abort_unless($request->user()->can('hr.manage'),403); abort_unless($task->organization_id===$this->organization($request)->id,404);
        $task->update(['completed_at'=>$task->completed_at ? null : now()]);
        return back()->with('success',$task->completed_at ? 'Checklist item reopened.' : 'Checklist item completed.');
    }

    public function caseStore(Request $request,HrEmployee $employee)
    {
        $this->enabled(); abort_unless($request->user()->can('hr.cases.manage') && $request->user()->can('hr.sensitive.manage'),403); $employee=$this->employee($request,$employee);
        $data=$request->validate(['opened_on'=>['required','date'],'category'=>['required','string','max:60'],'status'=>['required',Rule::in(['open','investigating','closed'])],'details'=>['required','string','max:10000'],'resolution'=>['nullable','string','max:10000']]);
        $case=$employee->cases()->create([...$data,'organization_id'=>$employee->organization_id,'created_by'=>$request->user()->id]);
        AdminAudit::record('hr.case.created',HrDisciplinaryCase::class,$case->id,null,['employee_id'=>$employee->id,'category'=>$case->category,'status'=>$case->status],$employee->organization_id);
        return back()->with('success','Restricted disciplinary case saved.');
    }

    public function trainingCourse(Request $request)
    {
        $this->enabled(); abort_unless($request->user()->can('hr.training.manage'),403); $org=$this->organization($request);
        $data=$request->validate(['name'=>['required','string','max:180',Rule::unique('hr_training_courses')->where('organization_id',$org->id)],'provider'=>['nullable','string','max:180'],'description'=>['nullable','string','max:3000']]);
        HrTrainingCourse::create([...$data,'organization_id'=>$org->id,'created_by'=>$request->user()->id]);
        return back()->with('success','Training course added.');
    }

    public function trainingRecord(Request $request,HrEmployee $employee)
    {
        $this->enabled(); abort_unless($request->user()->can('hr.training.manage'),403); $employee=$this->employee($request,$employee);
        $data=$request->validate(['course_id'=>['required','integer'],'status'=>['required',Rule::in(['planned','in_progress','completed','expired'])],'started_on'=>['nullable','date'],'completed_on'=>['nullable','date'],'expires_on'=>['nullable','date'],'certificate_document_id'=>['nullable','integer']]);
        abort_unless(HrTrainingCourse::where('organization_id',$employee->organization_id)->whereKey($data['course_id'])->exists(),422,'Choose a course in this organization.');
        if (! empty($data['certificate_document_id'])) abort_unless(HrEmployeeDocument::where('organization_id',$employee->organization_id)->where('employee_id',$employee->id)->where('category','certificate')->whereKey($data['certificate_document_id'])->exists(),422,'Certificate must be an employee document classified as a certificate.');
        HrTrainingRecord::updateOrCreate(['employee_id'=>$employee->id,'course_id'=>$data['course_id']], [...$data,'organization_id'=>$employee->organization_id,'recorded_by'=>$request->user()->id]);
        return back()->with('success','Employee training record saved.');
    }

    public function caseUpdate(Request $request,HrDisciplinaryCase $case)
    {
        $this->enabled(); abort_unless($request->user()->can('hr.cases.manage') && $request->user()->can('hr.sensitive.manage'),403); abort_unless($case->organization_id===$this->organization($request)->id,404);
        $data=$request->validate(['status'=>['required',Rule::in(['open','investigating','closed'])],'resolution'=>['nullable','string','max:10000']]);
        $case->update($data); AdminAudit::record('hr.case.updated',HrDisciplinaryCase::class,$case->id,null,['status'=>$case->status],$case->organization_id);
        return back()->with('success','Restricted case updated.');
    }
}
