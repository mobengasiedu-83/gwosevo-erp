<?php

namespace App\Http\Controllers\Organization;

use App\Domain\Administration\Settings;
use App\Domain\Organization\Assignments;
use App\Domain\Organization\Structure;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\CostCentre;
use App\Models\Department;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Position;
use App\Models\Team;
use Illuminate\Http\Request;

class StructureController extends Controller
{
    private function organization(Request $request): ?Organization
    {
        return $request->route('organization') ? Organization::findOrFail($request->route('organization')) : null;
    }

    private function access(Organization $organization): void
    {
        abort_unless(auth()->user()->isSuperAdmin() || ($organization->is_active && auth()->user()->organizations()->where('organizations.id', $organization->id)->exists()), 403);
    }

    public function index(Request $request)
    {
        $organizations = Organization::with('legalEntity')->when(! $request->user()->isSuperAdmin(), fn ($q) => $q->where('is_active', true)->whereHas('users', fn ($q) => $q->where('users.id', $request->user()->id)))->orderBy('name')->get();

        $section = $request->query('section') === 'departments' ? 'departments' : 'organization';

        return view('organization.index', compact('organizations', 'section'));
    }

    public function overview(Request $request)
    {
        $organization = $this->organization($request);
        $this->access($organization);
        $members = OrganizationMembership::with(['user', 'department', 'team', 'branch', 'position', 'manager', 'approvalManager', 'allocations.costCentre'])->where('organization_id', $organization->id)->orderBy('id')->get();
        $kind = $request->input('kind') === 'approval' ? 'approval' : 'reporting';
        $people = $members->filter(fn ($m) => $m->user->status === 'active')->keyBy('user_id');
        $children = [];
        foreach ($people as $member) {
            $parent = $kind === 'approval' ? ($member->approval_manager_id ?: $member->manager_id) : $member->manager_id;
            $children[isset($people[$parent]) ? $parent : 0][] = $member;
        }

        return view('organization.overview', compact('organization', 'members', 'people', 'children', 'kind'));
    }

    private function context(Request $request, Structure $structure): array
    {
        $type = $request->route('type');
        [$class,$title,$singular] = $structure->definition($type);
        $organization = $this->organization($request);
        abort_unless($structure->scoped($type) === (bool) $organization, 404);
        $query = $class::query()->when($organization, fn ($q) => $q->where('organization_id', $organization->id));
        $record = $request->route('record') ? (clone $query)->findOrFail($request->route('record')) : new $class;

        return compact('type', 'class', 'title', 'singular', 'organization', 'query', 'record');
    }

    public function catalog(Request $request, Structure $structure)
    {
        $context = $this->context($request, $structure);
        $records = $context['query']->when($request->filled('search'), fn ($q) => $q->where(fn ($q) => $q->where('code', 'like', '%'.$request->input('search').'%')->orWhere($context['type'] === 'positions' ? 'title' : 'name', 'like', '%'.$request->input('search').'%')))->orderBy('code')->paginate((int) app(Settings::class)->get('pagination', 25))->withQueryString();

        return view('organization.catalog', [...$context, 'records' => $records]);
    }

    public function form(Request $request, Structure $structure)
    {
        $context = $this->context($request, $structure);

        return view('organization.form', [...$context, 'fields' => $structure->fields($context['type']), 'choices' => $structure->choices($context['type'], $context['organization'], $context['record'])]);
    }

    public function save(Request $request, Structure $structure)
    {
        $context = $this->context($request, $structure);
        $structure->save($context['type'], $request->all(), $context['organization'], $context['record']);

        return redirect($this->catalogUrl($context['type'], $context['organization']))->with('success', $context['singular'].' saved.');
    }

    public function catalogUrl(string $type, ?Organization $organization): string
    {
        return $organization ? route('structure.units.index', [$organization->id, $type]) : route('structure.catalog.index', [$type]);
    }

    public function assignment(Request $request)
    {
        $organization = $this->organization($request);
        $member = OrganizationMembership::with(['user', 'allocations'])->where('organization_id', $organization->id)->findOrFail($request->route('membership'));
        $choices = [];
        foreach (['department_id' => Department::class, 'team_id' => Team::class, 'branch_id' => Branch::class, 'position_id' => Position::class, 'cost_centre_id' => CostCentre::class] as $key => $class) {
            $choices[$key] = $class::where('organization_id', $organization->id)->where('is_active', true)->orderBy('code')->get();
        }
        $managers = OrganizationMembership::with('user')->where('organization_id', $organization->id)->where('user_id', '!=', $member->user_id)->whereHas('user', fn ($q) => $q->where('status', 'active'))->get();

        return view('organization.assignment', compact('organization', 'member', 'choices', 'managers'));
    }

    public function assign(Request $request, Assignments $assignments)
    {
        $organization = $this->organization($request);
        $member = OrganizationMembership::where('organization_id', $organization->id)->findOrFail($request->route('membership'));
        $input = $request->all();
        $input['allocations'] = array_values(array_filter($input['allocations'] ?? [], fn ($a) => ! empty($a['cost_centre_id']) || ! empty($a['percentage'])));
        $assignments->save($member, $input);

        return to_route('structure.overview', $organization)->with('success', 'Organization assignment saved.');
    }
}
