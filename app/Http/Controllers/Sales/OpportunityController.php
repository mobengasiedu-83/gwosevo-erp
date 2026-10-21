<?php

namespace App\Http\Controllers\Sales;

use App\Domain\Sales\OpportunityService;
use App\Domain\Sales\SalesAccess;
use App\Domain\Administration\AdminAudit;
use App\Http\Controllers\Controller;
use App\Models\Opportunity;
use App\Models\OpportunityForecastSnapshot;
use App\Models\OpportunityCompetitor;
use App\Models\SalesPipelineStage;
use App\Models\SalesServiceLine;
use App\Models\SalesServicePackage;
use App\Models\SalesTarget;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class OpportunityController extends Controller
{
    public function index(Request $request, SalesAccess $access, OpportunityService $service)
    {
        $viewMode = $request->routeIs('opportunities.pipeline') ? 'pipeline' : 'opportunities';
        $this->authorize('viewAny', Opportunity::class);
        $organizationId = (int) $request->user()->active_organization_id;
        $service->ensureStages($organizationId);
        $query = $access->query($request->user())->with(['owner', 'account', 'serviceLine', 'servicePackage'])
            ->when($request->filled('stage'), fn ($q) => $q->where('stage', $request->string('stage')))
            ->when($request->filled('owner_id'), fn ($q) => $q->where('owner_id', $request->integer('owner_id')))
            ->when($request->filled('search'), fn ($q) => $q->where(fn ($s) => $s->where('name', 'like', '%'.$request->search.'%')->orWhere('reference', 'like', '%'.$request->search.'%')));
        $stages = SalesPipelineStage::where('organization_id', $organizationId)->where('is_active', true)->orderBy('sort_order')->get();
        $board = (clone $query)->whereNotIn('stage', ['won', 'lost'])->latest()->get()->groupBy('stage');
        $opportunities = $query->latest()->paginate(20)->withQueryString();
        $pipelineTotal = (clone $access->query($request->user()))->whereNotIn('stage', ['won', 'lost'])->sum('amount');
        $weightedTotal = (clone $access->query($request->user()))->whereNotIn('stage', ['won', 'lost'])->selectRaw('COALESCE(SUM(amount * probability / 100),0) as total')->value('total');
        $owners = User::where('status', 'active')->whereHas('organizations', fn ($q) => $q->whereKey($organizationId))->orderBy('name')->get();
        return view('sales.opportunities.index', compact('opportunities', 'stages', 'board', 'pipelineTotal', 'weightedTotal', 'owners', 'viewMode'));
    }

    public function create(Request $request, OpportunityService $service)
    {
        $this->authorize('create', Opportunity::class);
        $organizationId = (int) $request->user()->active_organization_id;
        $service->ensureStages($organizationId);
        return view('sales.opportunities.form', ['opportunity' => new Opportunity(['stage' => 'new']), ...$this->options($organizationId)]);
    }

    public function store(Request $request, OpportunityService $service)
    {
        $this->authorize('create', Opportunity::class);
        $data = $this->validateOpportunity($request, (int) $request->user()->active_organization_id);
        abort_if(! empty($data['owner_id']) && ! $request->user()->can('opportunities.assign'), 403);
        $opportunity = $service->create([...$data, 'organization_id' => $request->user()->active_organization_id], $request->user());
        return to_route('opportunities.show', $opportunity)->with('success', 'Opportunity created.');
    }

    public function show(Opportunity $opportunity, Request $request)
    {
        $this->authorize('view', $opportunity);
        $opportunity->load(['account', 'contact', 'lead', 'owner', 'serviceLine', 'servicePackage', 'activities.creator', 'stageHistory.actor', 'competitors', 'discountApprovals.requester', 'discountApprovals.decider']);
        $stages = SalesPipelineStage::where('organization_id', $opportunity->organization_id)->where('is_active', true)->orderBy('sort_order')->get();
        return view('sales.opportunities.show', compact('opportunity', 'stages'));
    }

    public function edit(Opportunity $opportunity)
    {
        $this->authorize('update', $opportunity);
        return view('sales.opportunities.form', ['opportunity' => $opportunity, ...$this->options($opportunity->organization_id)]);
    }

    public function update(Request $request, Opportunity $opportunity, OpportunityService $service)
    {
        $this->authorize('update', $opportunity);
        $data = $this->validateOpportunity($request, $opportunity->organization_id);
        abort_if((int) ($data['owner_id'] ?? 0) !== (int) $opportunity->owner_id && ! $request->user()->can('opportunities.assign'), 403);
        $service->update($opportunity, $data, $request->user());
        return to_route('opportunities.show', $opportunity)->with('success', 'Opportunity updated.');
    }

    public function stage(Request $request, Opportunity $opportunity, OpportunityService $service)
    {
        $this->authorize('update', $opportunity);
        $data = $request->validate(['stage' => ['required', Rule::exists('sales_pipeline_stages', 'key')->where('organization_id', $opportunity->organization_id)->where('is_active', true)], 'loss_reason' => ['nullable', 'string', 'max:2000']]);
        $service->update($opportunity, ['stage' => $data['stage'], 'loss_reason' => $data['loss_reason'] ?? $opportunity->loss_reason], $request->user());
        return back()->with('success', 'Pipeline stage updated.');
    }

    public function activity(Request $request, Opportunity $opportunity, OpportunityService $service)
    {
        $this->authorize('update', $opportunity);
        $data = $request->validate(['type' => ['required', Rule::in(['call', 'meeting', 'note', 'task'])], 'subject' => ['required', 'string', 'max:255'], 'body' => ['nullable', 'string', 'max:10000'], 'due_at' => ['nullable', 'date']]);
        $service->addActivity($opportunity, $data, $request->user());
        return back()->with('success', 'Opportunity activity added.');
    }

    public function completeActivity(Request $request, Opportunity $opportunity, int $activity)
    {
        $this->authorize('update', $opportunity);
        $item = $opportunity->activities()->findOrFail($activity);
        $item->update(['completed_at' => now()]);
        return back()->with('success', 'Next action completed.');
    }

    public function competitor(Request $request, Opportunity $opportunity, OpportunityService $service)
    {
        $this->authorize('update', $opportunity);
        $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'notes' => ['nullable', 'string', 'max:3000']]);
        $service->addCompetitor($opportunity, $data, $request->user());
        return back()->with('success', 'Competitor recorded.');
    }

    public function approvalRequest(Request $request, Opportunity $opportunity, OpportunityService $service)
    {
        $this->authorize('update', $opportunity);
        abort_if($opportunity->discountApprovals()->where('status', 'pending')->exists(), 409, 'An approval is already pending.');
        $data = $request->validate(['discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'], 'terms' => ['nullable', 'string', 'max:5000'], 'justification' => ['required', 'string', 'max:3000']]);
        $service->requestApproval($opportunity, $data, $request->user());
        return back()->with('success', 'Commercial approval requested.');
    }

    public function approvalDecision(Request $request, Opportunity $opportunity, int $approval, OpportunityService $service)
    {
        $this->authorize('approve', $opportunity);
        $data = $request->validate(['decision' => ['required', Rule::in(['approved', 'rejected'])], 'decision_note' => ['nullable', 'string', 'max:3000']]);
        $service->decideApproval($opportunity, $approval, $data['decision'], $data['decision_note'] ?? null, $request->user());
        return back()->with('success', 'Approval decision recorded.');
    }

    public function catalog(Request $request, OpportunityService $service)
    {
        $this->authorize('manageCatalog', Opportunity::class);
        $organizationId = (int) $request->user()->active_organization_id;
        $service->ensureStages($organizationId);
        $lines = SalesServiceLine::with('packages')->where('organization_id', $organizationId)->orderBy('sort_order')->get();
        $stages = SalesPipelineStage::where('organization_id', $organizationId)->orderBy('sort_order')->get();
        return view('sales.catalog', compact('lines', 'stages'));
    }

    public function saveLine(Request $request)
    {
        $this->authorize('manageCatalog', Opportunity::class);
        $organizationId = (int) $request->user()->active_organization_id;
        if (SalesServiceLine::where('organization_id', $organizationId)->count() >= 4) return back()->withErrors(['name' => 'The specification defines four strategic service lines. Update an existing entry instead of adding a fifth.'])->withInput();
        $data = $request->validate(['name' => ['required', 'string', 'max:150'], 'code' => ['required', 'alpha_dash', 'max:50', Rule::unique('sales_service_lines')->where('organization_id', $organizationId)], 'description' => ['nullable', 'string', 'max:3000']]);
        $line = SalesServiceLine::create([...$data, 'organization_id' => $organizationId]);
        AdminAudit::record('sales.catalog.line.created', SalesServiceLine::class, $line->id, null, $data, $organizationId);
        return back()->with('success', 'Service line added.');
    }

    public function savePackage(Request $request)
    {
        $this->authorize('manageCatalog', Opportunity::class);
        $organizationId = (int) $request->user()->active_organization_id;
        if (SalesServicePackage::where('organization_id', $organizationId)->count() >= 7) return back()->withErrors(['name' => 'The specification defines seven integrated service packages. Update an existing entry instead of adding an eighth.'])->withInput();
        $data = $request->validate(['name' => ['required', 'string', 'max:150'], 'code' => ['required', 'alpha_dash', 'max:50', Rule::unique('sales_service_packages')->where('organization_id', $organizationId)], 'service_line_id' => ['nullable', Rule::exists('sales_service_lines', 'id')->where('organization_id', $organizationId)], 'description' => ['nullable', 'string', 'max:3000']]);
        $package = SalesServicePackage::create([...$data, 'organization_id' => $organizationId]);
        AdminAudit::record('sales.catalog.package.created', SalesServicePackage::class, $package->id, null, $data, $organizationId);
        return back()->with('success', 'Integrated service package added.');
    }

    public function updateLine(Request $request, SalesServiceLine $line)
    {
        $this->authorize('manageCatalog', Opportunity::class);
        abort_unless($line->organization_id === (int) $request->user()->active_organization_id, 404);
        $data = $request->validate(['name' => ['required', 'string', 'max:150'], 'code' => ['required', 'alpha_dash', 'max:50', Rule::unique('sales_service_lines')->where('organization_id', $line->organization_id)->ignore($line->id)], 'description' => ['nullable', 'string', 'max:3000'], 'is_active' => ['required', 'boolean']]);
        $old = $line->only(array_keys($data));
        $line->update($data);
        AdminAudit::record('sales.catalog.line.updated', SalesServiceLine::class, $line->id, $old, $data, $line->organization_id);
        return back()->with('success', 'Service line updated.');
    }

    public function updatePackage(Request $request, SalesServicePackage $package)
    {
        $this->authorize('manageCatalog', Opportunity::class);
        abort_unless($package->organization_id === (int) $request->user()->active_organization_id, 404);
        $data = $request->validate(['name' => ['required', 'string', 'max:150'], 'code' => ['required', 'alpha_dash', 'max:50', Rule::unique('sales_service_packages')->where('organization_id', $package->organization_id)->ignore($package->id)], 'service_line_id' => ['nullable', Rule::exists('sales_service_lines', 'id')->where('organization_id', $package->organization_id)], 'description' => ['nullable', 'string', 'max:3000'], 'is_active' => ['required', 'boolean']]);
        $old = $package->only(array_keys($data));
        $package->update($data);
        AdminAudit::record('sales.catalog.package.updated', SalesServicePackage::class, $package->id, $old, $data, $package->organization_id);
        return back()->with('success', 'Integrated service package updated.');
    }

    public function updateStage(Request $request, SalesPipelineStage $stage)
    {
        $this->authorize('manageCatalog', Opportunity::class);
        abort_unless($stage->organization_id === (int) $request->user()->active_organization_id, 404);
        $data = $request->validate(['name' => ['required', 'string', 'max:100'], 'probability' => ['required', 'integer', 'min:0', 'max:100'], 'sort_order' => ['required', 'integer', 'min:1', 'max:100'], 'is_active' => ['required', 'boolean']]);
        if (! $data['is_active'] && Opportunity::where('organization_id', $stage->organization_id)->where('stage', $stage->key)->exists()) throw ValidationException::withMessages(['is_active' => 'Move open opportunities to another stage before deactivating this stage.']);
        $old = $stage->only(array_keys($data));
        $stage->update($data);
        Opportunity::where('organization_id', $stage->organization_id)->where('stage', $stage->key)->update(['probability' => $data['probability']]);
        AdminAudit::record('sales.pipeline.stage.updated', SalesPipelineStage::class, $stage->id, $old, $data, $stage->organization_id);
        return back()->with('success', 'Pipeline stage settings saved.');
    }

    public function targets(Request $request)
    {
        $this->authorize('manageTargets', Opportunity::class);
        $organizationId = (int) $request->user()->active_organization_id;
        $targets = SalesTarget::with(['user', 'team'])->where('organization_id', $organizationId)->orderByDesc('period_start')->paginate(20);
        $users = User::where('status', 'active')->whereHas('organizations', fn ($q) => $q->whereKey($organizationId))->orderBy('name')->get();
        $teams = Team::where('organization_id', $organizationId)->where('is_active', true)->orderBy('name')->get();
        return view('sales.targets', compact('targets', 'users', 'teams'));
    }

    public function saveTarget(Request $request)
    {
        $this->authorize('manageTargets', Opportunity::class);
        $organizationId = (int) $request->user()->active_organization_id;
        $data = $request->validate(['user_id' => ['nullable', Rule::exists('user_organization_memberships', 'user_id')->where('organization_id', $organizationId)], 'team_id' => ['nullable', Rule::exists('teams', 'id')->where('organization_id', $organizationId)], 'period_start' => ['required', 'date'], 'period_end' => ['required', 'date', 'after_or_equal:period_start'], 'revenue_target' => ['required', 'numeric', 'min:0'], 'pipeline_target' => ['required', 'numeric', 'min:0']]);
        if (empty($data['user_id']) === empty($data['team_id'])) return back()->withErrors(['user_id' => 'Choose exactly one person or team for this target.'])->withInput();
        $target = SalesTarget::create([...$data, 'organization_id' => $organizationId, 'created_by' => $request->user()->id]);
        AdminAudit::record('sales.target.created', SalesTarget::class, $target->id, null, $data, $organizationId);
        return back()->with('success', 'Sales target saved.');
    }

    public function report(Request $request, SalesAccess $access)
    {
        $this->authorize('viewReports', Opportunity::class);
        $base = $access->query($request->user());
        $stages = SalesPipelineStage::where('organization_id', $request->user()->active_organization_id)->orderBy('sort_order')->get();
        $stageTotals = (clone $base)->selectRaw('stage, COUNT(*) total, COALESCE(SUM(amount),0) amount, COALESCE(SUM(amount * probability / 100),0) weighted')->groupBy('stage')->get()->keyBy('stage');
        $closingForecast = (clone $base)->whereNotIn('stage', ['won', 'lost'])->whereNotNull('expected_close_date')->get(['expected_close_date', 'amount', 'probability'])->groupBy(fn ($item) => $item->expected_close_date->format('Y-m'))->map(fn ($items) => ['opportunities' => $items->count(), 'amount' => $items->sum(fn ($item) => (float) $item->amount), 'weighted' => $items->sum(fn ($item) => (float) $item->amount * $item->probability / 100)]);
        $offeringTotals = (clone $base)->selectRaw('service_line_id, service_package_id, COUNT(*) total, COALESCE(SUM(amount),0) amount, COALESCE(SUM(amount * probability / 100),0) weighted')->groupBy('service_line_id', 'service_package_id')->get()->map(function ($item) {
            $item->line_name = $item->service_line_id ? SalesServiceLine::find($item->service_line_id)?->name : null;
            $item->package_name = $item->service_package_id ? SalesServicePackage::find($item->service_package_id)?->name : null;
            return $item;
        });
        $competitorTotals = OpportunityCompetitor::query()->whereIn('opportunity_competitors.opportunity_id', (clone $base)->select('opportunities.id'))
            ->join('opportunities as scoped_opportunities', 'scoped_opportunities.id', '=', 'opportunity_competitors.opportunity_id')
            ->selectRaw('opportunity_competitors.name, COUNT(DISTINCT scoped_opportunities.id) opportunities, COUNT(DISTINCT CASE WHEN scoped_opportunities.stage = "won" THEN scoped_opportunities.id END) wins, COUNT(DISTINCT CASE WHEN scoped_opportunities.stage = "lost" THEN scoped_opportunities.id END) losses')
            ->groupBy('opportunity_competitors.name')->orderByDesc('opportunities')->get();
        $won = (clone $base)->where('stage', 'won');
        $closed = (clone $base)->whereIn('stage', ['won', 'lost']);
        $totalClosed = (clone $closed)->count();
        $winRate = $totalClosed ? round($won->count() * 100 / $totalClosed, 1) : 0;
        $velocity = (clone $won)->whereNotNull('closed_at')->get(['created_at', 'closed_at'])->avg(fn ($o) => $o->created_at->diffInDays($o->closed_at));
        $snapshotRows = OpportunityForecastSnapshot::whereIn('opportunity_id', (clone $base)->select('opportunities.id'))
            ->whereDate('forecast_month', '<', today()->startOfMonth())
            ->whereColumn('snapshot_date', '<=', 'forecast_month')
            ->orderBy('snapshot_date')->get()->groupBy(fn ($row) => $row->forecast_month->format('Y-m-01'));
        $actuals = (clone $won)->whereNotNull('closed_at')->get(['amount', 'closed_at'])->groupBy(fn ($o) => $o->closed_at->copy()->startOfMonth()->format('Y-m-01'))->map(fn ($items) => $items->sum(fn ($o) => (float) $o->amount));
        $accuracyValues = $snapshotRows->map(function ($rows, $month) use ($actuals) {
            $forecast = $rows->groupBy('opportunity_id')->sum(fn ($versions) => (float) $versions->last()->weighted_amount);
            $actual = (float) $actuals->get($month, 0);
            $denominator = max($forecast, $actual);
            return $denominator > 0 ? max(0, 100 * (1 - abs($forecast - $actual) / $denominator)) : 100;
        });
        $forecastAccuracy = $accuracyValues->isNotEmpty() ? round($accuracyValues->avg(), 1) : null;
        $targetQuery = SalesTarget::where('organization_id', $request->user()->active_organization_id)->whereDate('period_start', '<=', today())->whereDate('period_end', '>=', today());
        $user = $request->user();
        if (! $user->can('opportunities.targets.manage') && ! $user->hasRole('Administrator')) {
            $membership = $user->organizations()->whereKey($user->active_organization_id)->first()?->pivot;
            if (! $membership) $targetQuery->whereRaw('1 = 0');
            elseif ($membership->scope === 'own') $targetQuery->where('user_id', $user->id);
            elseif ($membership->scope === 'team' && $membership->team_id) {
                $teamUsers = \Illuminate\Support\Facades\DB::table('user_organization_memberships')->where('organization_id', $user->active_organization_id)->where('team_id', $membership->team_id)->select('user_id');
                $targetQuery->where(fn ($q) => $q->whereIn('user_id', $teamUsers)->orWhere('team_id', $membership->team_id));
            }
            elseif ($membership->scope === 'department' && $membership->department_id) {
                $departmentTeams = Team::where('organization_id', $user->active_organization_id)->where('department_id', $membership->department_id)->select('id');
                $departmentUsers = \App\Models\OrganizationMembership::where('organization_id', $user->active_organization_id)->where('department_id', $membership->department_id)->select('user_id');
                $targetQuery->where(fn ($q) => $q->whereIn('user_id', $departmentUsers)->orWhereIn('team_id', $departmentTeams));
            } elseif ($membership->scope !== 'organization') $targetQuery->whereRaw('1 = 0');
        }
        $targets = $targetQuery->get();
        foreach ($targets as $target) {
            $targetQuery = clone $base;
            if ($target->user_id) $targetQuery->where(fn ($q) => $q->where('owner_id', $target->user_id)->orWhere(fn ($q) => $q->whereNull('owner_id')->where('created_by', $target->user_id)));
            else {
                $memberIds = \Illuminate\Support\Facades\DB::table('user_organization_memberships')->where('organization_id', $target->organization_id)->where('team_id', $target->team_id)->select('user_id');
                $targetQuery->where(fn ($q) => $q->whereIn('owner_id', clone $memberIds)->orWhere(fn ($q) => $q->whereNull('owner_id')->whereIn('created_by', clone $memberIds)));
            }
            $period = clone $targetQuery;
            $target->actual_revenue = (clone $period)->where('stage', 'won')->whereBetween('closed_at', [$target->period_start->startOfDay(), $target->period_end->endOfDay()])->sum('amount');
            $target->open_pipeline = (clone $period)->whereNotIn('stage', ['won', 'lost'])->whereBetween('expected_close_date', [$target->period_start->toDateString(), $target->period_end->toDateString()])->sum('amount');
        }
        $report = compact('stageTotals', 'totalClosed', 'winRate', 'velocity', 'targets');
        if ($request->query('export') === 'csv') {
            $rows = (clone $base)->with(['owner', 'account'])->orderBy('expected_close_date')->get();
            return response()->streamDownload(function () use ($rows) { $out = fopen('php://output', 'w'); fputcsv($out, ['Reference', 'Opportunity', 'Account', 'Stage', 'Amount', 'Probability', 'Weighted amount', 'Expected close', 'Owner']); foreach ($rows as $o) fputcsv($out, [$o->reference, $o->name, $o->account?->name, $o->stage, $o->amount, $o->probability, round((float)$o->amount * $o->probability / 100, 2), $o->expected_close_date?->toDateString(), $o->owner?->name]); fclose($out); }, 'sales-pipeline.csv');
        }
        return view('sales.report', compact('stages', 'stageTotals', 'closingForecast', 'offeringTotals', 'competitorTotals', 'winRate', 'velocity', 'forecastAccuracy', 'targets'));
    }

    private function validateOpportunity(Request $request, int $organizationId): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'], 'description' => ['nullable', 'string', 'max:10000'],
            'stage' => ['required', Rule::exists('sales_pipeline_stages', 'key')->where('organization_id', $organizationId)->where('is_active', true)],
            'amount' => ['nullable', 'numeric', 'min:0', 'max:9999999999999.99'], 'expected_close_date' => ['nullable', 'date'],
            'owner_id' => ['nullable', Rule::exists('user_organization_memberships', 'user_id')->where('organization_id', $organizationId)],
            'account_id' => ['nullable', Rule::exists('accounts', 'id')->where('organization_id', $organizationId)],
            'contact_id' => ['nullable', Rule::exists('contacts', 'id')->where('organization_id', $organizationId)],
            'service_line_id' => ['nullable', Rule::exists('sales_service_lines', 'id')->where('organization_id', $organizationId)->where('is_active', true)],
            'service_package_id' => ['nullable', Rule::exists('sales_service_packages', 'id')->where('organization_id', $organizationId)->where('is_active', true)],
            'loss_reason' => ['nullable', 'string', 'max:2000'], 'nonstandard_terms' => ['nullable', 'string', 'max:5000'],
        ]);
        if (! empty($data['service_package_id'])) {
            $package = SalesServicePackage::where('organization_id', $organizationId)->findOrFail($data['service_package_id']);
            if (! empty($data['service_line_id']) && $package->service_line_id && (int) $package->service_line_id !== (int) $data['service_line_id']) throw ValidationException::withMessages(['service_package_id' => 'Select a package under the chosen service line.']);
        }
        if (! empty($data['owner_id']) && ! User::whereKey($data['owner_id'])->where('status', 'active')->exists()) throw ValidationException::withMessages(['owner_id' => 'Choose an active user in this organization.']);
        if (! empty($data['contact_id']) && ! empty($data['account_id'])) {
            $contact = \App\Models\Contact::where('organization_id', $organizationId)->findOrFail($data['contact_id']);
            if ($contact->account_id && (int) $contact->account_id !== (int) $data['account_id']) throw ValidationException::withMessages(['contact_id' => 'The selected contact belongs to a different account.']);
        }
        return $data;
    }

    private function options(int $organizationId): array
    {
        return [
            'stages' => SalesPipelineStage::where('organization_id', $organizationId)->where('is_active', true)->orderBy('sort_order')->get(),
            'lines' => SalesServiceLine::where('organization_id', $organizationId)->where('is_active', true)->orderBy('sort_order')->get(),
            'packages' => SalesServicePackage::where('organization_id', $organizationId)->where('is_active', true)->orderBy('sort_order')->get(),
            'accounts' => \App\Models\Account::where('organization_id', $organizationId)->orderBy('name')->get(),
            'contacts' => \App\Models\Contact::where('organization_id', $organizationId)->orderBy('last_name')->get(),
            'owners' => User::where('status', 'active')->whereHas('organizations', fn ($q) => $q->whereKey($organizationId))->orderBy('name')->get(),
        ];
    }
}
