<?php

namespace App\Http\Controllers;

use App\Domain\Administration\AdminAudit;
use App\Domain\Administration\Modules;
use App\Models\HrApplication;
use App\Models\HrAssessmentTemplate;
use App\Models\HrCandidate;
use App\Models\HrCandidateAssessment;
use App\Models\HrEmployee;
use App\Models\HrInterview;
use App\Models\HrOffer;
use App\Models\HrRequisition;
use App\Models\HrVacancy;
use App\Models\Organization;
use App\Models\Department;
use App\Models\Position;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class RecruitmentController extends Controller
{
    private function enabled(): void { abort_unless(app(Modules::class)->enabled('recruitment'), 404); }

    private function organization(Request $request): Organization
    {
        $id = (int) ($request->input('organization_id') ?: $request->query('organization_id') ?: $request->user()->active_organization_id ?: $request->user()->organizations()->orderBy('organizations.name')->value('organizations.id'));
        abort_unless($id && $request->user()->canAccessOrganization($id), 403);
        return Organization::findOrFail($id);
    }

    private function scopedApplication(Request $request, HrApplication $application): HrApplication
    {
        abort_unless($application->organization_id === $this->organization($request)->id, 404);
        return $application;
    }

    public function index(Request $request)
    {
        $this->enabled();
        abort_unless($request->user()->can('recruitment.view'), 403);
        $org = $this->organization($request);
        $requisitions = HrRequisition::where('organization_id', $org->id)->latest()->paginate(10, ['*'], 'requisitions')->withQueryString();
        $vacancies = HrVacancy::where('organization_id', $org->id)->withCount('applications')->latest()->paginate(10, ['*'], 'vacancies')->withQueryString();
        $options = [
            'departments' => Department::where('organization_id', $org->id)->where('is_active', true)->orderBy('name')->get(),
            'positions' => Position::where('organization_id', $org->id)->where('is_active', true)->orderBy('title')->get(),
            'approved_requisitions' => HrRequisition::where('organization_id', $org->id)->where('status', 'approved')->orderByDesc('decided_at')->get(),
        ];
        $metrics = [
            'open' => HrVacancy::where('organization_id', $org->id)->where('status', 'open')->count(),
            'applications' => HrApplication::where('organization_id', $org->id)->count(),
            'in_review' => HrApplication::where('organization_id', $org->id)->whereIn('stage', ['screening','interview','assessment'])->count(),
            'offers' => HrOffer::where('organization_id', $org->id)->whereIn('status', ['pending_approval','issued','accepted'])->count(),
        ];
        $reports = $request->user()->can('recruitment.reports.view') ? HrApplication::where('organization_id', $org->id)->selectRaw('stage, COUNT(*) as total')->groupBy('stage')->pluck('total', 'stage') : null;
        $timeToHireDays = null;
        if ($reports) {
            $durations = HrApplication::where('organization_id',$org->id)->where('stage','hired')->get(['stage_history'])->map(function($application) {
                $events=$application->stage_history ?? []; $applied=collect($events)->firstWhere('stage','new')['at'] ?? null; $hired=collect($events)->last(fn($event)=>$event['stage']==='hired')['at'] ?? null;
                return $applied && $hired ? \Illuminate\Support\Carbon::parse($applied)->diffInDays(\Illuminate\Support\Carbon::parse($hired)) : null;
            })->filter();
            $timeToHireDays=$durations->isNotEmpty() ? round($durations->avg(),1) : null;
        }
        $organizations = $request->user()->isSuperAdmin() ? Organization::where('is_active', true)->orderBy('name')->get() : $request->user()->organizations()->where('organizations.is_active', true)->orderBy('organizations.name')->get();
        return view('recruitment.index', compact('org','organizations','requisitions','vacancies','options','metrics','reports','timeToHireDays'));
    }

    public function requisitionStore(Request $request)
    {
        $this->enabled(); abort_unless($request->user()->can('recruitment.manage'), 403); $org = $this->organization($request);
        $data = $request->validate(['title'=>['required','string','max:180'],'department_id'=>['nullable','integer'],'position_id'=>['nullable','integer'],'headcount'=>['required','integer','min:1','max:250'],'justification'=>['required','string','max:5000']]);
        foreach (['department_id'=>Department::class,'position_id'=>Position::class] as $key=>$class) if (!empty($data[$key])) abort_unless($class::where('organization_id',$org->id)->whereKey($data[$key])->exists(),422,'Select a record belonging to this organization.');
        $reference = 'REQ-'.now()->format('ymd').'-'.Str::upper(Str::random(6));
        $item = HrRequisition::create([...$data,'organization_id'=>$org->id,'reference'=>$reference,'status'=>'pending','requested_by'=>$request->user()->id]);
        AdminAudit::record('recruitment.requisition.created', HrRequisition::class, $item->id, null, ['reference'=>$reference,'status'=>'pending'], $org->id);
        return back()->with('success','Hiring requisition submitted for approval.');
    }

    public function requisitionDecide(Request $request, HrRequisition $requisition)
    {
        $this->enabled(); abort_unless($request->user()->can('recruitment.requisitions.approve'),403);
        abort_unless($requisition->organization_id === $this->organization($request)->id,404);
        $data=$request->validate(['decision'=>['required',Rule::in(['approved','rejected'])]]);
        abort_unless($requisition->status==='pending',422,'Only pending requisitions can be decided.');
        abort_unless((int) $requisition->requested_by !== (int) $request->user()->id,403,'The person who submitted a requisition cannot approve it.');
        $old=$requisition->status; $requisition->update(['status'=>$data['decision'],'decided_by'=>$request->user()->id,'decided_at'=>now()]);
        AdminAudit::record('recruitment.requisition.decided',HrRequisition::class,$requisition->id,['status'=>$old],['status'=>$requisition->status],$requisition->organization_id);
        return back()->with('success','Hiring requisition '.$data['decision'].'.');
    }

    public function vacancyStore(Request $request)
    {
        $this->enabled(); abort_unless($request->user()->can('recruitment.manage'),403); $org=$this->organization($request);
        $data=$request->validate(['requisition_id'=>['required','integer'],'title'=>['required','string','max:180'],'description'=>['required','string','max:12000'],'requirements'=>['nullable','string','max:12000'],'opens_on'=>['nullable','date'],'closes_on'=>['nullable','date','after_or_equal:opens_on'],'status'=>['required',Rule::in(['draft','open'])]]);
        $req=HrRequisition::where('organization_id',$org->id)->where('status','approved')->findOrFail($data['requisition_id']);
        $slug=Str::slug($data['title']).'-'.Str::lower(Str::random(10));
        $vacancy=HrVacancy::create([...$data,'organization_id'=>$org->id,'requisition_id'=>$req->id,'slug'=>$slug,'created_by'=>$request->user()->id]);
        AdminAudit::record('recruitment.vacancy.created',HrVacancy::class,$vacancy->id,null,['title'=>$vacancy->title,'status'=>$vacancy->status],$org->id);
        return back()->with('success','Vacancy created. '.($vacancy->status==='open' ? 'Public application link: '.route('careers.show',$vacancy->slug) : 'Publish it by setting its status to open.'));
    }

    public function vacancyUpdate(Request $request, HrVacancy $vacancy)
    {
        $this->enabled(); abort_unless($request->user()->can('recruitment.manage'),403); abort_unless($vacancy->organization_id===$this->organization($request)->id,404);
        $data=$request->validate(['status'=>['required',Rule::in(['draft','open','closed'])],'closes_on'=>['nullable','date']]);
        $vacancy->update($data); AdminAudit::record('recruitment.vacancy.updated',HrVacancy::class,$vacancy->id,null,$data,$vacancy->organization_id);
        return back()->with('success','Vacancy status updated.');
    }

    public function careerShow(string $slug)
    {
        $this->enabled();
        $vacancy=HrVacancy::where('slug',$slug)->where('status','open')->where(fn($q)=>$q->whereNull('opens_on')->orWhere('opens_on','<=',today()))->where(fn($q)=>$q->whereNull('closes_on')->orWhere('closes_on','>=',today()))->firstOrFail();
        return view('recruitment.career',compact('vacancy'));
    }

    public function apply(Request $request,string $slug)
    {
        $this->enabled();
        $vacancy=HrVacancy::where('slug',$slug)->where('status','open')->where(fn($q)=>$q->whereNull('opens_on')->orWhere('opens_on','<=',today()))->where(fn($q)=>$q->whereNull('closes_on')->orWhere('closes_on','>=',today()))->firstOrFail();
        $data=$request->validate(['name'=>['required','string','max:180'],'email'=>['required','email','max:254'],'phone'=>['nullable','string','max:60'],'cover_letter'=>['nullable','string','max:10000'],'resume'=>['nullable','file','mimes:pdf,doc,docx','max:10240'],'consent'=>['accepted']]);
        $candidate=HrCandidate::create(['organization_id'=>$vacancy->organization_id,'profile'=>['name'=>$data['name'],'email'=>$data['email'],'phone'=>$data['phone'] ?? null],'consent_at'=>now()]);
        $path=null;
        if ($request->hasFile('resume')) { $file=$request->file('resume'); $path='recruitment/private/'.Str::ulid().'.enc'; Storage::disk('local')->put($path,Crypt::encryptString(file_get_contents($file->getRealPath()))); }
        $application=HrApplication::create(['organization_id'=>$vacancy->organization_id,'vacancy_id'=>$vacancy->id,'candidate_id'=>$candidate->id,'stage'=>'new','cover_letter'=>$data['cover_letter'] ?? null,'resume_path'=>$path,'stage_history'=>[['stage'=>'new','at'=>now()->toIso8601String()]],'applied_at'=>now()]);
        AdminAudit::record('recruitment.application.received',HrApplication::class,$application->id,null,['vacancy_id'=>$vacancy->id,'stage'=>'new'],$vacancy->organization_id);
        return view('recruitment.received',compact('vacancy'));
    }

    public function vacancyShow(Request $request,HrVacancy $vacancy)
    {
        $this->enabled(); abort_unless($request->user()->can('recruitment.applications.view'),403); abort_unless($vacancy->organization_id===$this->organization($request)->id,404);
        $vacancy->load(['requisition','applications.candidate','applications.interviews','applications.assessments.template','applications.offers']);
        $templates=HrAssessmentTemplate::where('organization_id',$vacancy->organization_id)->where('is_active',true)->orderBy('name')->get();
        return view('recruitment.vacancy',compact('vacancy','templates'));
    }

    public function applicationStage(Request $request,HrApplication $application)
    {
        $this->enabled(); abort_unless($request->user()->can('recruitment.applications.manage'),403); $application=$this->scopedApplication($request,$application);
        $data=$request->validate(['stage'=>['required',Rule::in(['screening','interview','assessment','offer','rejected','withdrawn'])],'screening_notes'=>['nullable','string','max:5000']]);
        $history=$application->stage_history ?? []; $history[]=['stage'=>$data['stage'],'at'=>now()->toIso8601String(),'by'=>$request->user()->id];
        $application->update(['stage'=>$data['stage'],'screening_notes'=>$data['screening_notes'] ?? $application->screening_notes,'stage_history'=>$history]);
        AdminAudit::record('recruitment.application.stage_changed',HrApplication::class,$application->id,null,['stage'=>$data['stage']],$application->organization_id);
        return back()->with('success','Candidate stage updated.');
    }

    public function resume(Request $request,HrApplication $application)
    {
        $this->enabled(); abort_unless($request->user()->can('recruitment.applications.view'),403); $application=$this->scopedApplication($request,$application); abort_unless($application->resume_path,404);
        $file=Crypt::decryptString(Storage::disk('local')->get($application->resume_path));
        return response($file,200,['Content-Type'=>'application/octet-stream','Content-Disposition'=>'attachment; filename="candidate-resume"','X-Content-Type-Options'=>'nosniff','Cache-Control'=>'private, no-store']);
    }

    public function interviewStore(Request $request,HrApplication $application)
    {
        $this->enabled(); abort_unless($request->user()->can('recruitment.interviews.manage'),403); $application=$this->scopedApplication($request,$application);
        $data=$request->validate(['scheduled_at'=>['required','date'],'format'=>['required',Rule::in(['panel','phone','video','practical'])],'interviewer_id'=>['nullable','integer'],'panel_notes'=>['nullable','string','max:10000']]);
        if(!empty($data['interviewer_id'])) abort_unless(User::where('status','active')->whereHas('organizations',fn($q)=>$q->where('organizations.id',$application->organization_id))->whereKey($data['interviewer_id'])->exists(),422,'Interviewer must be an active member of the organization.');
        $interview=$application->interviews()->create([...$data,'organization_id'=>$application->organization_id,'status'=>'scheduled']);
        AdminAudit::record('recruitment.interview.scheduled',HrInterview::class,$interview->id,null,['application_id'=>$application->id,'scheduled_at'=>$interview->scheduled_at],$application->organization_id);
        return back()->with('success','Interview scheduled.');
    }

    public function interviewScore(Request $request,HrInterview $interview)
    {
        $this->enabled(); abort_unless($request->user()->can('recruitment.interviews.manage'),403); abort_unless($interview->organization_id===$this->organization($request)->id,404);
        $data=$request->validate(['score'=>['required','integer','min:0','max:100'],'status'=>['required',Rule::in(['completed','cancelled'])],'panel_notes'=>['nullable','string','max:10000']]);
        $interview->update($data); AdminAudit::record('recruitment.interview.scored',HrInterview::class,$interview->id,null,['score'=>$interview->score,'status'=>$interview->status],$interview->organization_id);
        return back()->with('success','Interview evaluation saved.');
    }

    public function templateStore(Request $request)
    {
        $this->enabled(); abort_unless($request->user()->can('recruitment.assessments.manage'),403); $org=$this->organization($request);
        $data=$request->validate(['name'=>['required','string','max:180',Rule::unique('hr_assessment_templates')->where('organization_id',$org->id)],'assessment_type'=>['required',Rule::in(['online','practical','speech'])],'instructions'=>['nullable','string','max:5000'],'questions'=>['required','string','max:20000'],'duration_minutes'=>['required','integer','min:1','max:480'],'passing_score'=>['nullable','integer','min:0','max:100']]);
        $questions=array_values(array_filter(array_map('trim',preg_split('/\R/',$data['questions'])))); abort_if(count($questions)>100,422,'Limit each assessment to 100 questions or criteria.');
        HrAssessmentTemplate::create([...$data,'organization_id'=>$org->id,'questions'=>$questions,'created_by'=>$request->user()->id]);
        return back()->with('success','Assessment template saved.');
    }

    public function assessmentAssign(Request $request,HrApplication $application)
    {
        $this->enabled(); abort_unless($request->user()->can('recruitment.assessments.manage'),403); $application=$this->scopedApplication($request,$application);
        $data=$request->validate(['template_id'=>['required','integer']]); $template=HrAssessmentTemplate::where('organization_id',$application->organization_id)->where('is_active',true)->findOrFail($data['template_id']);
        $token=Str::random(64); $assignment=$application->assessments()->create(['organization_id'=>$application->organization_id,'template_id'=>$template->id,'access_token_hash'=>hash('sha256',$token),'status'=>'assigned']);
        $history=$application->stage_history ?? []; $history[]=['stage'=>'assessment','at'=>now()->toIso8601String(),'by'=>$request->user()->id]; $application->update(['stage'=>'assessment','stage_history'=>$history]);
        AdminAudit::record('recruitment.assessment.assigned',HrCandidateAssessment::class,$assignment->id,null,['application_id'=>$application->id,'template_id'=>$template->id],$application->organization_id);
        return back()->with('success','Assessment assigned. Share this candidate link with them: '.route('candidate-assessments.show',$token));
    }

    public function candidateAssessmentShow(string $token)
    {
        $this->enabled(); $assignment=HrCandidateAssessment::where('access_token_hash',hash('sha256',$token))->with('template','application.vacancy','application.candidate')->firstOrFail();
        abort_unless(in_array($assignment->status,['assigned','in_progress'],true),410);
        if (!$assignment->started_at) $assignment->update(['started_at'=>now(),'expires_at'=>now()->addMinutes($assignment->template->duration_minutes),'status'=>'in_progress']);
        if ($assignment->expires_at->isPast()) abort(410,'The assessment time has expired.');
        return view('recruitment.assessment',compact('assignment','token'));
    }

    public function candidateAssessmentSubmit(Request $request,string $token)
    {
        $this->enabled(); $assignment=HrCandidateAssessment::where('access_token_hash',hash('sha256',$token))->with('template')->firstOrFail();
        abort_unless($assignment->status==='in_progress' && $assignment->expires_at?->isFuture(),410,'This assessment is no longer accepting submissions.');
        $rules=['answers'=>['nullable','array','max:100'],'answers.*'=>['nullable','string','max:5000'],'artifact'=>['nullable','file','mimes:mp3,wav,m4a,mp4,pdf,doc,docx','max:20480']];
        $data=$request->validate($rules); $path=null;
        if($request->hasFile('artifact')) { $file=$request->file('artifact'); $path='recruitment/private/'.Str::ulid().'.enc'; Storage::disk('local')->put($path,Crypt::encryptString(file_get_contents($file->getRealPath()))); }
        $assignment->update(['responses'=>$data['answers'] ?? [],'artifact_path'=>$path,'submitted_at'=>now(),'status'=>'submitted']);
        return view('recruitment.assessment-received');
    }

    public function assessmentScore(Request $request,HrCandidateAssessment $assignment)
    {
        $this->enabled(); abort_unless($request->user()->can('recruitment.assessments.manage'),403); abort_unless($assignment->organization_id===$this->organization($request)->id,404);
        $data=$request->validate(['score'=>['required','integer','min:0','max:100'],'assessor_notes'=>['nullable','string','max:10000']]);
        abort_unless($assignment->status==='submitted',422,'Only submitted assessments can be graded.');
        $assignment->update([...$data,'status'=>isset($assignment->template->passing_score)&&$data['score'] >= $assignment->template->passing_score ? 'passed' : 'graded','assessed_by'=>$request->user()->id]);
        return back()->with('success','Assessment score recorded.');
    }

    public function assessmentArtifact(Request $request,HrCandidateAssessment $assignment)
    {
        $this->enabled(); abort_unless($request->user()->can('recruitment.assessments.manage'),403); abort_unless($assignment->organization_id===$this->organization($request)->id,404); abort_unless($assignment->artifact_path,404);
        $contents=Crypt::decryptString(Storage::disk('local')->get($assignment->artifact_path));
        return response($contents,200,['Content-Type'=>'application/octet-stream','Content-Disposition'=>'attachment; filename="candidate-assessment-submission"','X-Content-Type-Options'=>'nosniff','Cache-Control'=>'private, no-store']);
    }

    public function offerStore(Request $request,HrApplication $application)
    {
        $this->enabled(); abort_unless($request->user()->can('recruitment.offers.manage'),403); $application=$this->scopedApplication($request,$application);
        abort_unless(!in_array($application->stage,['rejected','withdrawn','hired'],true),422,'This application cannot receive an offer.');
        $data=$request->validate(['terms'=>['required','string','max:12000'],'valid_until'=>['nullable','date','after_or_equal:today']]);
        $offer=$application->offers()->create([...$data,'organization_id'=>$application->organization_id,'status'=>'pending_approval','created_by'=>$request->user()->id]);
        $application->update(['stage'=>'offer']);
        AdminAudit::record('recruitment.offer.created',HrOffer::class,$offer->id,null,['application_id'=>$application->id,'status'=>'pending_approval'],$application->organization_id);
        return back()->with('success','Offer draft submitted for approval.');
    }

    public function offerApprove(Request $request,HrOffer $offer)
    {
        $this->enabled(); abort_unless($request->user()->can('recruitment.offers.approve'),403); abort_unless($offer->organization_id===$this->organization($request)->id,404);
        abort_unless($offer->status==='pending_approval',422,'Only pending offers can be approved.'); $token=Str::random(64);
        abort_unless((int) $offer->created_by !== (int) $request->user()->id,403,'The person who prepared an offer cannot approve it.');
        $offer->update(['status'=>'issued','approved_by'=>$request->user()->id,'approved_at'=>now(),'response_token_hash'=>hash('sha256',$token)]);
        AdminAudit::record('recruitment.offer.issued',HrOffer::class,$offer->id,['status'=>'pending_approval'],['status'=>'issued'],$offer->organization_id);
        return back()->with('success','Offer approved and issued. Share this response link with the candidate: '.route('candidate-offers.show',$token));
    }

    public function candidateOfferShow(string $token)
    {
        $this->enabled(); $offer=HrOffer::where('response_token_hash',hash('sha256',$token))->where('status','issued')->where(fn($q)=>$q->whereNull('valid_until')->orWhere('valid_until','>=',today()))->with('application.vacancy')->firstOrFail();
        return view('recruitment.offer-response',compact('offer','token'));
    }

    public function candidateOfferRespond(Request $request,string $token)
    {
        $this->enabled(); $offer=HrOffer::where('response_token_hash',hash('sha256',$token))->where('status','issued')->firstOrFail();
        abort_unless(!$offer->valid_until || !$offer->valid_until->lt(today()),410,'This offer has expired.');
        $data=$request->validate(['decision'=>['required',Rule::in(['accepted','declined'])]]); $offer->update(['status'=>$data['decision'],'responded_at'=>now()]);
        if($data['decision']==='accepted') $offer->application()->update(['stage'=>'offer']);
        AdminAudit::record('recruitment.offer.responded',HrOffer::class,$offer->id,null,['status'=>$offer->status],$offer->organization_id);
        return view('recruitment.offer-received',compact('offer'));
    }

    public function hire(Request $request,HrApplication $application)
    {
        $this->enabled(); abort_unless($request->user()->can('recruitment.hire'),403); $application=$this->scopedApplication($request,$application);
        abort_unless($application->offers()->where('status','accepted')->exists(),422,'An accepted offer is required before converting the candidate to an employee.');
        abort_unless(!$application->hired_employee_id,409,'This candidate has already been converted.');
        $data=$request->validate(['employee_number'=>['required','string','max:50',Rule::unique('hr_employees')->where('organization_id',$application->organization_id)],'hire_date'=>['required','date'],'employment_type'=>['required',Rule::in(['permanent','fixed_term','contractor','intern','temporary'])],'user_id'=>['nullable','integer']]);
        if(!empty($data['user_id'])) abort_unless(User::where('status','active')->whereHas('organizations',fn($q)=>$q->where('organizations.id',$application->organization_id))->whereKey($data['user_id'])->exists(),422,'Linked account must be an active organization member.');
        $profile=$application->candidate->profile; $parts=preg_split('/\s+/',trim($profile['name'] ?? 'Candidate'),2);
        $employee=HrEmployee::create(['organization_id'=>$application->organization_id,'user_id'=>$data['user_id'] ?? null,'employee_number'=>$data['employee_number'],'first_name'=>$parts[0] ?: 'Candidate','last_name'=>$parts[1] ?? '','work_email'=>$profile['email'] ?? null,'work_phone'=>$profile['phone'] ?? null,'employment_type'=>$data['employment_type'],'status'=>'preboarding','hire_date'=>$data['hire_date'],'created_by'=>$request->user()->id]);
        $application->update(['stage'=>'hired','hired_employee_id'=>$employee->id]);
        $history=$application->stage_history ?? []; $history[]=['stage'=>'hired','at'=>now()->toIso8601String(),'by'=>$request->user()->id]; $application->update(['stage_history'=>$history]);
        AdminAudit::record('recruitment.candidate.hired',HrApplication::class,$application->id,null,['employee_id'=>$employee->id],$application->organization_id);
        return to_route('hr.employees.show',['employee'=>$employee,'organization_id'=>$employee->organization_id])->with('success','Candidate converted to an HR employee in preboarding status.');
    }
}
