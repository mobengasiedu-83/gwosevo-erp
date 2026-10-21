<?php

namespace App\Http\Controllers;

use App\Domain\Administration\Modules;
use App\Models\Organization;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProjectWorkspaceController extends Controller
{
    private function organization(Request $request): Organization
    {
        $id = (int) ($request->input('organization_id') ?: $request->query('organization_id') ?: $request->user()->active_organization_id ?: $request->user()->organizations()->orderBy('organizations.name')->value('organizations.id'));
        abort_unless($id && $request->user()->canAccessOrganization($id), 403);
        $organization = Organization::findOrFail($id);
        abort_unless($organization->is_active || $request->user()->isSuperAdmin(), 403);

        return $organization;
    }

    private function visibleProjectIds(Request $request, Organization $organization)
    {
        $user = $request->user();
        $query = DB::table('projects as p')->where('p.organization_id', $organization->id);
        if (! $user->isSuperAdmin() && ! $user->can('projects.manage')) {
            $query->where(function ($scope) use ($user) {
                $scope->where('p.owner_id', $user->id)
                    ->orWhereExists(fn ($members) => $members->selectRaw('1')->from('project_members as pm')->whereColumn('pm.project_id', 'p.id')->where('pm.user_id', $user->id))
                    ->orWhereExists(fn ($tasks) => $tasks->selectRaw('1')->from('project_tasks as pt')->whereColumn('pt.project_id', 'p.id')->where('pt.owner_id', $user->id));
            });
        }

        return $query->pluck('p.id');
    }

    public function show(Request $request, string $section)
    {
        abort_unless(app(Modules::class)->enabled('projects'), 404);
        abort_unless(in_array($section, ['tasks', 'milestones', 'timesheets', 'risks', 'deliverables', 'reports'], true), 404);
        $user = $request->user();
        if ($section === 'reports') {
            abort_unless($user->isSuperAdmin() || $user->can('projects.reports.view'), 403);
        } else {
            $requiredPermission = match ($section) {
                'tasks' => ['projects.view', 'projects.manage', 'projects.tasks.manage'],
                'milestones' => ['projects.view', 'projects.manage', 'projects.approve'],
                'timesheets' => ['projects.view', 'projects.manage', 'projects.time.manage'],
                'risks' => ['projects.view', 'projects.manage', 'projects.risks.manage'],
                default => ['projects.view', 'projects.manage', 'projects.approve'],
            };
            abort_unless($user->isSuperAdmin() || $user->canAny($requiredPermission), 403);
        }

        $organization = $this->organization($request);
        $projectIds = $this->visibleProjectIds($request, $organization);
        $title = match ($section) {
            'tasks' => 'Tasks',
            'milestones' => 'Milestones',
            'timesheets' => 'Timesheets',
            'risks' => 'Risks',
            'deliverables' => 'Deliverables',
            default => 'Operations Reports',
        };

        if ($section === 'reports') {
            $summary = [
                'projects' => DB::table('projects')->where('organization_id', $organization->id)->count(),
                'active' => DB::table('projects')->where('organization_id', $organization->id)->where('status', 'active')->count(),
                'open_tasks' => DB::table('project_tasks')->where('organization_id', $organization->id)->whereNotIn('status', ['done', 'cancelled'])->count(),
                'overdue_tasks' => DB::table('project_tasks')->where('organization_id', $organization->id)->whereNotIn('status', ['done', 'cancelled'])->whereDate('due_on', '<', today())->count(),
                'milestones' => DB::table('project_milestones')->where('organization_id', $organization->id)->count(),
                'open_risks' => DB::table('project_risks')->where('organization_id', $organization->id)->where('status', 'open')->count(),
                'hours' => (float) DB::table('project_time_entries')->where('organization_id', $organization->id)->sum('hours'),
            ];
            $taskStatuses = DB::table('project_tasks')->where('organization_id', $organization->id)->selectRaw('status, COUNT(*) as total')->groupBy('status')->orderBy('status')->get();
            $workload = DB::table('project_tasks as t')->join('users as u', 'u.id', '=', 't.owner_id')->where('t.organization_id', $organization->id)->whereNotIn('t.status', ['done', 'cancelled'])->groupBy('u.id', 'u.name')->orderBy('u.name')->select('u.name', DB::raw('COUNT(*) as task_count'))->get();
            return view('projects.workspace', compact('organization', 'section', 'title', 'summary', 'taskStatuses', 'workload'));
        }

        $records = match ($section) {
            'tasks' => DB::table('project_tasks as r')->join('projects as p', 'p.id', '=', 'r.project_id')->leftJoin('users as u', 'u.id', '=', 'r.owner_id')->where('r.organization_id', $organization->id)->whereIn('r.project_id', $projectIds)->select('r.*', 'p.name as project_name', 'u.name as owner_name')->orderByRaw("CASE WHEN r.status IN ('done', 'cancelled') THEN 1 ELSE 0 END")->orderBy('r.due_on')->paginate(30),
            'milestones' => DB::table('project_milestones as r')->join('projects as p', 'p.id', '=', 'r.project_id')->where('r.organization_id', $organization->id)->whereIn('r.project_id', $projectIds)->select('r.*', 'p.name as project_name')->orderBy('r.due_on')->paginate(30),
            'timesheets' => DB::table('project_time_entries as r')->join('projects as p', 'p.id', '=', 'r.project_id')->join('users as u', 'u.id', '=', 'r.user_id')->leftJoin('project_tasks as t', 't.id', '=', 'r.task_id')->where('r.organization_id', $organization->id)->whereIn('r.project_id', $projectIds)->select('r.*', 'p.name as project_name', 'u.name as user_name', 't.title as task_title')->orderByDesc('r.work_date')->paginate(30),
            'risks' => DB::table('project_risks as r')->join('projects as p', 'p.id', '=', 'r.project_id')->leftJoin('users as u', 'u.id', '=', 'r.owner_id')->where('r.organization_id', $organization->id)->whereIn('r.project_id', $projectIds)->select('r.*', 'p.name as project_name', 'u.name as owner_name')->orderByRaw("CASE WHEN r.status = 'open' THEN 0 ELSE 1 END")->orderByDesc('r.updated_at')->paginate(30),
            default => DB::table('project_tasks as r')->join('projects as p', 'p.id', '=', 'r.project_id')->leftJoin('users as u', 'u.id', '=', 'r.owner_id')->where('r.organization_id', $organization->id)->whereIn('r.project_id', $projectIds)->where('r.is_deliverable', true)->select('r.*', 'p.name as project_name', 'u.name as owner_name')->orderByRaw("CASE WHEN r.approval_status = 'pending' THEN 0 ELSE 1 END")->orderBy('r.due_on')->paginate(30),
        };

        $records->withQueryString();

        return view('projects.workspace', compact('organization', 'section', 'title', 'records'));
    }
}
