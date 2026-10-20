<?php

namespace App\Http\Controllers;

use App\Models\HrEmployee;
use App\Models\Organization;
use App\Models\User;
use App\Domain\Administration\AdminAudit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class PerformanceController extends Controller
{
    private function enabled(): void
    {
        abort_unless(app(\App\Domain\Administration\Modules::class)->enabled('performance'), 404);
    }

    private function organization(Request $request): Organization
    {
        $id = (int) ($request->input('organization_id') ?: $request->query('organization_id') ?: $request->user()->active_organization_id ?: $request->user()->organizations()->orderBy('organizations.name')->value('organizations.id'));
        $org = Organization::whereKey($id)->where('is_active', true)->firstOrFail();
        abort_unless($request->user()->isSuperAdmin() || $request->user()->organizations()->where('organizations.id', $org->id)->exists(), 404);
        return $org;
    }

    private function authorizeReview(Request $request, object $review): void
    {
        abort_unless((int) $review->organization_id === $this->organization($request)->id, 404);
        $employee = HrEmployee::where('organization_id', $review->organization_id)->findOrFail($review->employee_id);
        $isSelf = (int) $employee->user_id === (int) $request->user()->id;
        $isManager = (int) ($review->manager_user_id ?? 0) === (int) $request->user()->id;
        abort_unless($request->user()->isSuperAdmin() || $request->user()->can('performance.manage') || $isSelf || ($isManager && $request->user()->can('performance.reviews.manager')), 403);
    }

    public function index(Request $request)
    {
        $this->enabled();
        abort_unless($request->user()->can('performance.view'), 403);
        $org = $this->organization($request);
        $cycles = DB::table('hr_performance_cycles')->where('organization_id', $org->id)->orderByDesc('starts_on')->get();
        $reviewQuery = DB::table('hr_performance_reviews as r')->join('hr_performance_cycles as c', 'c.id', '=', 'r.cycle_id')->join('hr_employees as e', 'e.id', '=', 'r.employee_id')->where('r.organization_id', $org->id);
        if (! $request->user()->isSuperAdmin() && ! $request->user()->can('performance.manage') && ! $request->user()->can('performance.reports.view')) {
            $reviewQuery->where(fn ($q) => $q->where('e.user_id', $request->user()->id)->orWhere('r.manager_user_id', $request->user()->id));
        }
        $reviews = $reviewQuery->select('r.*', 'c.name as cycle_name', 'e.first_name', 'e.last_name', 'e.employee_number')->orderByDesc('r.updated_at')->limit(100)->get();
        $employees = HrEmployee::where('organization_id', $org->id)->where('status', 'active')->with('manager.user')->orderBy('last_name')->get();
        $kpis = DB::table('hr_performance_kpis')->where('organization_id', $org->id)->where('is_active', true)->orderBy('name')->get();
        $metrics = ['cycles' => $cycles->count(), 'open' => $cycles->where('status', 'open')->count(), 'reviews' => $reviews->count(), 'completed' => $reviews->whereIn('status', ['approved', 'closed'])->count()];
        $feedbackInbox = DB::table('hr_performance_feedback as f')->join('hr_performance_reviews as r', 'r.id', '=', 'f.review_id')->join('hr_employees as e', 'e.id', '=', 'r.employee_id')->join('hr_performance_cycles as c', 'c.id', '=', 'r.cycle_id')->where('f.reviewer_user_id', $request->user()->id)->whereNull('f.submitted_at')->select('f.*', 'e.first_name', 'e.last_name', 'c.name as cycle_name')->get();
        return view('performance.index', compact('org', 'cycles', 'reviews', 'employees', 'kpis', 'metrics', 'feedbackInbox'));
    }

    public function cycleStore(Request $request)
    {
        $this->enabled(); abort_unless($request->user()->can('performance.manage'), 403); $org = $this->organization($request);
        $data = $request->validate(['name' => ['required', 'string', 'max:180', Rule::unique('hr_performance_cycles')->where('organization_id', $org->id)], 'period_type' => ['required', Rule::in(['monthly', 'quarterly', 'biannual', 'annual'])], 'starts_on' => ['required', 'date'], 'ends_on' => ['required', 'date', 'after_or_equal:starts_on'], 'goal_weight' => ['required', 'integer', 'min:0', 'max:100'], 'competency_weight' => ['required', 'integer', 'min:0', 'max:100']]);
        abort_unless((int) $data['goal_weight'] + (int) $data['competency_weight'] === 100, 422, 'Goal and competency weights must total 100%.');
        $id = DB::table('hr_performance_cycles')->insertGetId([...$data, 'organization_id' => $org->id, 'status' => 'draft', 'created_by' => $request->user()->id, 'created_at' => now(), 'updated_at' => now()]);
        AdminAudit::record('performance.cycle.created', 'hr_performance_cycles', $id, null, ['name' => $data['name'], 'status' => 'draft'], $org->id);
        return back()->with('success', 'Performance cycle created. Open it to generate employee reviews.');
    }

    public function cycleOpen(Request $request, int $cycle)
    {
        $this->enabled(); abort_unless($request->user()->can('performance.manage'), 403); $org = $this->organization($request);
        $item = DB::table('hr_performance_cycles')->where('organization_id', $org->id)->where('status', 'draft')->find($cycle); abort_unless($item, 404);
        DB::transaction(function () use ($item, $org, $request) {
            foreach (HrEmployee::where('organization_id', $org->id)->where('status', 'active')->get() as $employee) {
                $manager = $employee->manager;
                DB::table('hr_performance_reviews')->insertOrIgnore(['organization_id' => $org->id, 'cycle_id' => $item->id, 'employee_id' => $employee->id, 'manager_employee_id' => $employee->manager_employee_id, 'manager_user_id' => $manager?->user_id, 'status' => 'self_review', 'created_at' => now(), 'updated_at' => now()]);
            }
            DB::table('hr_performance_cycles')->where('id', $item->id)->update(['status' => 'open', 'updated_at' => now()]);
        });
        AdminAudit::record('performance.cycle.opened', 'hr_performance_cycles', $item->id, ['status' => 'draft'], ['status' => 'open'], $org->id);
        return back()->with('success', 'Cycle opened and active employees were added.');
    }

    public function kpiStore(Request $request)
    {
        $this->enabled(); abort_unless($request->user()->can('performance.kpis.manage'), 403); $org = $this->organization($request);
        $data = $request->validate(['name' => ['required', 'string', 'max:180'], 'description' => ['nullable', 'string', 'max:3000'], 'measure' => ['nullable', 'string', 'max:120'], 'default_target' => ['nullable', 'numeric', 'min:0'], 'weight' => ['required', 'integer', 'min:1', 'max:100'], 'department_id' => ['nullable', 'integer'], 'position_id' => ['nullable', 'integer']]);
        foreach (['department_id' => 'departments', 'position_id' => 'positions'] as $key => $table) if (!empty($data[$key])) abort_unless(DB::table($table)->where('organization_id', $org->id)->where('id', $data[$key])->exists(), 422);
        $id = DB::table('hr_performance_kpis')->insertGetId([...$data, 'organization_id' => $org->id, 'is_active' => true, 'created_by' => $request->user()->id, 'created_at' => now(), 'updated_at' => now()]);
        AdminAudit::record('performance.kpi.created', 'hr_performance_kpis', $id, null, ['name' => $data['name']], $org->id);
        return back()->with('success', 'KPI added to the organization library.');
    }

    public function show(Request $request, int $review)
    {
        $this->enabled(); $item = DB::table('hr_performance_reviews')->find($review); abort_unless($item, 404); $this->authorizeReview($request, $item);
        $employee = HrEmployee::with('manager')->findOrFail($item->employee_id);
        $goals = DB::table('hr_performance_goals')->where('review_id', $item->id)->orderBy('id')->get();
        $evidence = DB::table('hr_performance_evidence')->where('review_id', $item->id)->orderByDesc('created_at')->get();
        $feedback = collect();
        if ($request->user()->can('performance.feedback.manage') || $request->user()->can('performance.manage')) {
            $feedback = DB::table('hr_performance_feedback as f')->join('users as u', 'u.id', '=', 'f.reviewer_user_id')->where('f.review_id', $item->id)->select('f.*', 'u.name as reviewer_name')->get();
        } elseif ((int) $employee->user_id === (int) $request->user()->id) {
            $feedback = DB::table('hr_performance_feedback as f')->join('users as u', 'u.id', '=', 'f.reviewer_user_id')->where('f.review_id', $item->id)->whereNotNull('f.submitted_at')->select('f.*', 'u.name as reviewer_name')->get();
        } else {
            $feedback = DB::table('hr_performance_feedback as f')->where('f.review_id', $item->id)->where('f.reviewer_user_id', $request->user()->id)->get();
        }
        $plans = DB::table('hr_performance_plans')->where('review_id', $item->id)->orderByDesc('created_at')->get();
        $cycle = DB::table('hr_performance_cycles')->find($item->cycle_id);
        return view('performance.show', compact('item', 'employee', 'goals', 'feedback', 'plans', 'cycle', 'evidence'));
    }

    public function selfReview(Request $request, int $review)
    {
        $this->enabled(); $item = DB::table('hr_performance_reviews')->find($review); abort_unless($item, 404); $this->authorizeReview($request, $item);
        $employee = HrEmployee::findOrFail($item->employee_id); abort_unless((int) $employee->user_id === (int) $request->user()->id || $request->user()->can('performance.manage'), 403);
        abort_unless($item->status === 'self_review', 422, 'Self-appraisal is closed for this review.');
        $data = $request->validate(['self_assessment' => ['required', 'string', 'max:12000'], 'self_score' => ['required', 'integer', 'min:0', 'max:100'], 'goals' => ['nullable', 'array', 'max:20'], 'goals.*.title' => ['nullable', 'string', 'max:180'], 'goals.*.description' => ['nullable', 'string', 'max:3000'], 'goals.*.target_value' => ['nullable', 'numeric', 'min:0'], 'goals.*.actual_value' => ['nullable', 'numeric', 'min:0'], 'goals.*.weight' => ['nullable', 'integer', 'min:1', 'max:100'], 'goals.*.due_on' => ['nullable', 'date'], 'competency_scores' => ['nullable', 'array'], 'competency_scores.*' => ['integer', 'min:0', 'max:100']]);
        $data['goals'] = array_values(array_filter($data['goals'] ?? [], fn ($goal) => filled($goal['title'] ?? null)));
        $cycle = DB::table('hr_performance_cycles')->find($item->cycle_id);
        DB::transaction(function () use ($item, $data, $cycle) {
            DB::table('hr_performance_goals')->where('review_id', $item->id)->delete();
            foreach ($data['goals'] ?? [] as $goal) DB::table('hr_performance_goals')->insert(['organization_id' => $item->organization_id, 'review_id' => $item->id, 'title' => $goal['title'], 'smart_description' => $goal['description'] ?? null, 'target_value' => $goal['target_value'] ?? null, 'actual_value' => $goal['actual_value'] ?? null, 'weight' => $goal['weight'] ?? 10, 'due_on' => $goal['due_on'] ?? null, 'status' => 'active', 'self_comment' => null, 'manager_comment' => null, 'attainment_score' => null, 'created_at' => now(), 'updated_at' => now()]);
            $competencies = $data['competency_scores'] ?? [];
            DB::table('hr_performance_reviews')->where('id', $item->id)->update(['self_assessment' => Crypt::encryptString($data['self_assessment']), 'self_score' => $data['self_score'], 'competency_scores' => Crypt::encryptString(json_encode($competencies)), 'status' => 'self_submitted', 'self_submitted_at' => now(), 'updated_at' => now()]);
        });
        AdminAudit::record('performance.self_review.submitted', 'hr_performance_reviews', $item->id, ['status' => 'self_review'], ['status' => 'self_submitted', 'self_score' => $data['self_score']], $item->organization_id);
        return back()->with('success', 'Self-appraisal submitted to your manager.');
    }

    public function managerReview(Request $request, int $review)
    {
        $this->enabled(); abort_unless($request->user()->can('performance.reviews.manager') || $request->user()->can('performance.manage'), 403); $item = DB::table('hr_performance_reviews')->find($review); abort_unless($item, 404); $this->authorizeReview($request, $item);
        abort_unless($request->user()->can('performance.manage') || (int) $item->manager_user_id === (int) $request->user()->id, 403, 'Only the employee’s assigned manager may submit this review.');
        abort_unless($item->status === 'self_submitted', 422, 'The employee must submit a self-appraisal first.');
        $data = $request->validate(['manager_assessment' => ['required', 'string', 'max:12000'], 'manager_score' => ['required', 'integer', 'min:0', 'max:100'], 'manager_competency_scores' => ['nullable', 'array'], 'manager_competency_scores.*' => ['integer', 'min:0', 'max:100'], 'goal_scores' => ['nullable', 'array'], 'goal_scores.*' => ['integer', 'min:0', 'max:100']]);
        foreach ($data['goal_scores'] ?? [] as $id => $score) DB::table('hr_performance_goals')->where('organization_id', $item->organization_id)->where('review_id', $item->id)->where('id', $id)->update(['attainment_score' => $score, 'updated_at' => now()]);
        $cycle = DB::table('hr_performance_cycles')->find($item->cycle_id);
        $goalScores = DB::table('hr_performance_goals')->where('review_id', $item->id)->whereNotNull('attainment_score')->get(['weight', 'attainment_score']);
        $goalScore = $goalScores->sum('weight') ? (int) round($goalScores->sum(fn ($g) => $g->weight * $g->attainment_score) / $goalScores->sum('weight')) : (int) $data['manager_score'];
        $competencies = $data['manager_competency_scores'] ?? (json_decode(Crypt::decryptString($item->competency_scores ?: Crypt::encryptString('{}')), true) ?: []);
        $competencyScore = count($competencies) ? (int) round(array_sum($competencies) / count($competencies)) : (int) $data['manager_score'];
        $final = (int) round(($goalScore * $cycle->goal_weight + $competencyScore * $cycle->competency_weight) / 100);
        DB::table('hr_performance_reviews')->where('id', $item->id)->update(['manager_assessment' => Crypt::encryptString($data['manager_assessment']), 'manager_competency_scores' => Crypt::encryptString(json_encode($competencies)), 'manager_score' => $data['manager_score'], 'calibrated_score' => $final, 'status' => 'calibration', 'manager_submitted_at' => now(), 'updated_at' => now()]);
        AdminAudit::record('performance.manager_review.submitted', 'hr_performance_reviews', $item->id, ['status' => 'self_submitted'], ['status' => 'calibration', 'weighted_score' => $final], $item->organization_id);
        return back()->with('success', 'Manager review submitted. Weighted score is '.$final.'%; HR can calibrate and approve it.');
    }

    public function calibrate(Request $request, int $review)
    {
        $this->enabled(); abort_unless($request->user()->can('performance.calibrate'), 403); $item = DB::table('hr_performance_reviews')->find($review); abort_unless($item, 404); abort_unless((int) $item->organization_id === $this->organization($request)->id, 404); abort_unless($item->status === 'calibration', 422);
        $data = $request->validate(['calibrated_score' => ['required', 'integer', 'min:0', 'max:100'], 'calibration_note' => ['required', 'string', 'max:5000']]);
        DB::table('hr_performance_reviews')->where('id', $item->id)->update([...$data, 'status' => 'pending_approval', 'calibrated_by' => $request->user()->id, 'updated_at' => now()]);
        AdminAudit::record('performance.review.calibrated', 'hr_performance_reviews', $item->id, ['calibrated_score' => $item->calibrated_score], ['calibrated_score' => $data['calibrated_score'], 'status' => 'pending_approval'], $item->organization_id);
        return back()->with('success', 'Calibrated score submitted for independent approval.');
    }

    public function approve(Request $request, int $review)
    {
        $this->enabled(); abort_unless($request->user()->can('performance.reviews.approve'), 403); $item = DB::table('hr_performance_reviews')->find($review); abort_unless($item, 404); abort_unless((int) $item->organization_id === $this->organization($request)->id, 404); abort_unless($item->status === 'pending_approval', 422); abort_unless((int) $item->manager_user_id !== (int) $request->user()->id, 403, 'The assigned manager cannot approve their own review.');
        DB::transaction(function () use ($item, $request) {
            DB::table('hr_performance_reviews')->where('id', $item->id)->update(['final_score' => $item->calibrated_score, 'status' => 'approved', 'approved_by' => $request->user()->id, 'approved_at' => now(), 'updated_at' => now()]);
            if (! DB::table('hr_performance_reviews')->where('cycle_id', $item->cycle_id)->where('status', '!=', 'approved')->exists()) {
                DB::table('hr_performance_cycles')->where('id', $item->cycle_id)->where('status', 'open')->update(['status' => 'closed', 'closed_by' => $request->user()->id, 'closed_at' => now(), 'updated_at' => now()]);
            }
        });
        AdminAudit::record('performance.review.approved', 'hr_performance_reviews', $item->id, ['status' => 'pending_approval'], ['status' => 'approved', 'final_score' => $item->calibrated_score], $item->organization_id);
        return back()->with('success', 'Performance review approved and added to the employee history.');
    }

    public function feedbackRequest(Request $request, int $review)
    {
        $this->enabled(); abort_unless($request->user()->can('performance.feedback.manage') || $request->user()->can('performance.manage'), 403); $item = DB::table('hr_performance_reviews')->find($review); abort_unless($item, 404); abort_unless((int) $item->organization_id === $this->organization($request)->id, 404);
        $data = $request->validate(['reviewer_user_id' => ['required', 'integer'], 'relationship' => ['required', Rule::in(['peer', 'stakeholder', 'direct_report'])]]);
        abort_unless(User::whereKey($data['reviewer_user_id'])->where('status', 'active')->whereHas('organizations', fn ($q) => $q->where('organizations.id', $item->organization_id))->exists(), 422, 'Feedback reviewer must be an active organization member.');
        abort_if((int) $data['reviewer_user_id'] === (int) HrEmployee::findOrFail($item->employee_id)->user_id, 422, 'Employees cannot provide feedback on their own review.');
        DB::table('hr_performance_feedback')->updateOrInsert(['review_id' => $item->id, 'reviewer_user_id' => $data['reviewer_user_id']], ['organization_id' => $item->organization_id, 'relationship' => $data['relationship'], 'updated_at' => now(), 'created_at' => now()]);
        AdminAudit::record('performance.feedback.requested', 'hr_performance_reviews', $item->id, null, ['reviewer_user_id' => $data['reviewer_user_id']], $item->organization_id);
        return back()->with('success', '360 feedback request recorded. The reviewer can submit it from their assigned review link.');
    }

    public function feedbackRespond(Request $request, int $feedback)
    {
        $this->enabled(); abort_unless($request->user()->can('performance.feedback.respond'), 403); $item = DB::table('hr_performance_feedback')->find($feedback); abort_unless($item && (int) $item->reviewer_user_id === (int) $request->user()->id, 404);
        $data = $request->validate(['score' => ['required', 'integer', 'min:0', 'max:100'], 'response' => ['required', 'string', 'max:5000']]);
        DB::table('hr_performance_feedback')->where('id', $item->id)->update(['score' => $data['score'], 'response' => Crypt::encryptString($data['response']), 'submitted_at' => now(), 'updated_at' => now()]);
        AdminAudit::record('performance.feedback.submitted', 'hr_performance_feedback', $item->id, null, ['score' => $data['score']], $item->organization_id);
        return back()->with('success', '360 feedback submitted.');
    }

    public function planStore(Request $request, int $review)
    {
        $this->enabled(); abort_unless($request->user()->can('performance.plans.manage'), 403); $item = DB::table('hr_performance_reviews')->find($review); abort_unless($item, 404); abort_unless((int) $item->organization_id === $this->organization($request)->id, 404);
        $data = $request->validate(['plan_type' => ['required', Rule::in(['improvement', 'development'])], 'title' => ['required', 'string', 'max:180'], 'actions' => ['required', 'string', 'max:10000'], 'starts_on' => ['nullable', 'date'], 'due_on' => ['nullable', 'date', 'after_or_equal:starts_on'], 'course_id' => ['nullable', 'integer']]);
        if (!empty($data['course_id'])) abort_unless(DB::table('hr_training_courses')->where('organization_id', $item->organization_id)->where('id', $data['course_id'])->exists(), 422);
        $id = DB::table('hr_performance_plans')->insertGetId(['organization_id' => $item->organization_id, 'review_id' => $item->id, ...$data, 'actions' => Crypt::encryptString($data['actions']), 'status' => 'active', 'created_by' => $request->user()->id, 'created_at' => now(), 'updated_at' => now()]);
        AdminAudit::record('performance.plan.created', 'hr_performance_plans', $id, null, ['plan_type' => $data['plan_type'], 'title' => $data['title']], $item->organization_id);
        return back()->with('success', ucfirst($data['plan_type']).' plan created.');
    }

    public function evidenceStore(Request $request, int $review)
    {
        $this->enabled(); $item = DB::table('hr_performance_reviews')->find($review); abort_unless($item, 404); $this->authorizeReview($request, $item);
        abort_unless($request->user()->can('performance.manage') || $request->user()->can('performance.reviews.self') || $request->user()->can('performance.reviews.manager'), 403);
        $data = $request->validate(['evidence' => ['required', 'file', 'max:10240', 'mimes:pdf,doc,docx,xls,xlsx,png,jpg,jpeg,txt']]);
        $file = $data['evidence']; $path = 'performance-evidence/'.$item->organization_id.'/'.$item->id.'/'.\Illuminate\Support\Str::uuid().'.enc';
        Storage::disk('local')->put($path, Crypt::encryptString(file_get_contents($file->getRealPath())));
        $id = DB::table('hr_performance_evidence')->insertGetId(['organization_id' => $item->organization_id, 'review_id' => $item->id, 'original_name' => basename($file->getClientOriginalName()), 'mime_type' => $file->getMimeType() ?: 'application/octet-stream', 'size_bytes' => $file->getSize(), 'storage_path' => $path, 'uploaded_by' => $request->user()->id, 'created_at' => now(), 'updated_at' => now()]);
        AdminAudit::record('performance.evidence.uploaded', 'hr_performance_evidence', $id, null, ['review_id' => $item->id, 'name' => basename($file->getClientOriginalName())], $item->organization_id);
        return back()->with('success', 'Evidence uploaded to encrypted private storage.');
    }

    public function evidenceDownload(Request $request, int $evidence)
    {
        $this->enabled(); $entry = DB::table('hr_performance_evidence')->find($evidence); abort_unless($entry, 404); $review = DB::table('hr_performance_reviews')->find($entry->review_id); abort_unless($review, 404); $this->authorizeReview($request, $review);
        $contents = Crypt::decryptString(Storage::disk('local')->get($entry->storage_path));
        AdminAudit::record('performance.evidence.downloaded', 'hr_performance_evidence', $entry->id, null, ['review_id' => $review->id], $entry->organization_id);
        return response($contents)->header('Content-Type', $entry->mime_type)->header('Content-Disposition', 'attachment; filename="'.addcslashes($entry->original_name, '"\\').'"')->header('X-Content-Type-Options', 'nosniff');
    }
}
