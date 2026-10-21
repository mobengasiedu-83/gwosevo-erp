<?php

namespace App\Http\Controllers;

use App\Domain\Administration\AdminAudit;
use App\Domain\Administration\Modules;
use App\Domain\Reports\BusinessReports;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ReportsController extends Controller
{
    private function organization(Request $request): int
    {
        abort_unless(app(Modules::class)->enabled('reports'), 404);
        $id = (int) $request->user()->active_organization_id;
        abort_unless($id && $request->user()->canAccessOrganization($id), 403);
        return $id;
    }

    private function canManage(Request $request): bool
    {
        return $request->user()->isSuperAdmin() || $request->user()->can('reports.custom.manage');
    }

    public function index(Request $request, BusinessReports $reports)
    {
        $organizationId = $this->organization($request);
        $user = $request->user();
        $sources = $reports->available($user);
        abort_unless($user->isSuperAdmin() || $user->canAny(['reports.dashboard.view', 'reports.custom.manage', 'reports.kpis.manage', 'reports.schedules.manage']) || $sources !== [], 403);
        $dates = $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date', 'after_or_equal:from']]);
        $from = $dates['from'] ?? now()->subDays(29)->toDateString();
        $to = $dates['to'] ?? today()->toDateString();
        $widgets = [];
        foreach ($sources as $key => $source) {
            $widgets[] = ['label' => $source['label'], 'count' => $reports->aggregate($user, $organizationId, ['dataset' => $key, 'measure' => 'count', 'from' => $from, 'to' => $to])];
        }
        $kpis = DB::table('bi_kpi_definitions')->where('organization_id', $organizationId)->orderBy('name')->get()
            ->filter(fn ($kpi) => isset($sources[$kpi->dataset]))
            ->map(function ($kpi) use ($reports, $user, $organizationId, $from, $to) {
                $value = $reports->aggregate($user, $organizationId, ['dataset' => $kpi->dataset, 'measure' => $kpi->measure, 'filter_field' => $kpi->filter_field, 'filter_value' => $kpi->filter_value, 'from' => $from, 'to' => $to]);
                return (object) [...(array) $kpi, 'value' => $value, 'progress' => $kpi->target > 0 ? min(100, round($value / $kpi->target * 100)) : null];
            })->values();
        $savedReports = DB::table('bi_custom_reports')->where('organization_id', $organizationId)
            ->when(! $this->canManage($request), fn ($q) => $q->where('created_by', $user->id))->orderByDesc('updated_at')->get();
        $schedules = $this->canManage($request)
            ? DB::table('bi_report_schedules as s')->join('bi_custom_reports as r', 'r.id', '=', 's.report_id')->where('s.organization_id', $organizationId)->where('s.is_active', true)->select('s.*', 'r.name as report_name')->orderBy('s.next_run_at')->get()
            : DB::table('bi_report_schedules as s')->join('bi_custom_reports as r', 'r.id', '=', 's.report_id')->where('s.organization_id', $organizationId)->where('s.created_by', $user->id)->where('s.is_active', true)->select('s.*', 'r.name as report_name')->orderBy('s.next_run_at')->get();
        $section = (string) $request->query('section', 'executive');
        abort_unless(in_array($section, ['executive', 'custom', 'scheduled', 'exports'], true), 404);
        return view('reports.index', compact('sources', 'widgets', 'kpis', 'savedReports', 'schedules', 'organizationId', 'from', 'to', 'section'));
    }

    public function section(Request $request, string $section, BusinessReports $reports)
    {
        abort_unless(in_array($section, ['executive', 'custom', 'scheduled', 'exports'], true), 404);
        $request->query->set('section', $section);
        return $this->index($request, $reports);
    }

    public function store(Request $request, BusinessReports $reports)
    {
        $organizationId = $this->organization($request);
        abort_unless($this->canManage($request), 403);
        $data = $request->validate(['name' => ['required', 'string', 'max:160'], 'dataset' => ['required', 'string', 'max:40'], 'group_by' => ['nullable', 'string', 'max:80'], 'measure' => ['required', 'string', 'max:80'], 'columns_text' => ['nullable', 'string', 'max:500'], 'filter_field' => ['nullable', 'string', 'max:80'], 'filter_value' => ['nullable', 'string', 'max:180'], 'from' => ['nullable', 'date'], 'to' => ['nullable', 'date', 'after_or_equal:from']]);
        $source = $reports->sourceFor($request->user(), $data['dataset']);
        $columns = collect(explode(',', $data['columns_text'] ?? ''))->map(fn ($column) => trim($column))->filter()->unique()->take(12)->values()->all();
        abort_unless(isset($source['measures'][$data['measure']]), 422, 'Choose an available report measure.');
        if ($data['dataset'] === 'invoices' && $data['measure'] === 'sum:total' && ($data['group_by'] ?? '') !== 'currency' && (($data['filter_field'] ?? null) !== 'currency' || ($data['filter_value'] ?? '') === '')) {
            throw ValidationException::withMessages(['measure' => 'Group finance totals by currency or filter to one currency before summing.']);
        }
        abort_if(($data['group_by'] ?? '') !== '' && ! isset($source['groups'][$data['group_by']]), 422, 'Choose an available grouping.');
        foreach ($columns as $column) abort_unless(isset($source['columns'][$column]), 422, 'Choose report columns from the selected data source.');
        if (! empty($data['filter_field'])) abort_unless(isset($source['columns'][$data['filter_field']]), 422, 'Choose a filter field from the selected data source.');
        $definition = collect($data)->only(['dataset', 'group_by', 'measure', 'filter_field', 'filter_value', 'from', 'to'])->toArray();
        $definition['columns'] = $columns;
        $id = DB::table('bi_custom_reports')->insertGetId(['organization_id' => $organizationId, 'name' => $data['name'], 'dataset' => $data['dataset'], 'definition' => json_encode($definition, JSON_THROW_ON_ERROR), 'created_by' => $request->user()->id, 'created_at' => now(), 'updated_at' => now()]);
        AdminAudit::record('reports.custom.created', 'bi_custom_reports', $id, null, ['name' => $data['name'], 'dataset' => $data['dataset']], $organizationId);
        return redirect()->route('reports.run', $id)->with('success', 'Custom report saved.');
    }

    private function report(Request $request, int $id): object
    {
        $report = DB::table('bi_custom_reports')->where('organization_id', $this->organization($request))->where('id', $id)->first() ?: abort(404);
        abort_unless($this->canManage($request) || (int) $report->created_by === (int) $request->user()->id, 403);
        return $report;
    }

    public function run(Request $request, int $report, BusinessReports $reports)
    {
        $item = $this->report($request, $report);
        $definition = json_decode($item->definition, true, flags: JSON_THROW_ON_ERROR);
        $sources = $reports->available($request->user());
        abort_unless(isset($sources[$item->dataset]), 403);
        $rows = $reports->rows($request->user(), (int) $item->organization_id, $definition);
        DB::table('bi_report_runs')->insert(['organization_id' => $item->organization_id, 'report_id' => $item->id, 'requested_by' => $request->user()->id, 'status' => 'success', 'row_count' => count($rows), 'created_at' => now(), 'updated_at' => now()]);
        return view('reports.show', ['report' => $item, 'source' => $sources[$item->dataset], 'rows' => $rows]);
    }

    public function export(Request $request, int $report, BusinessReports $reports)
    {
        $item = $this->report($request, $report);
        $definition = json_decode($item->definition, true, flags: JSON_THROW_ON_ERROR);
        $rows = $reports->rows($request->user(), (int) $item->organization_id, $definition);
        $csv = $reports->csv($rows);
        DB::table('bi_report_runs')->insert(['organization_id' => $item->organization_id, 'report_id' => $item->id, 'requested_by' => $request->user()->id, 'status' => 'exported', 'row_count' => count($rows), 'created_at' => now(), 'updated_at' => now()]);
        return response($csv, 200, ['Content-Type' => 'text/csv; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="report-'.$item->id.'-'.today()->format('Ymd').'.csv"']);
    }

    public function storeKpi(Request $request, BusinessReports $reports)
    {
        $organizationId = $this->organization($request); abort_unless($request->user()->isSuperAdmin() || $request->user()->can('reports.kpis.manage'), 403);
        $data = $request->validate(['name' => ['required', 'string', 'max:140'], 'dataset' => ['required', 'string', 'max:40'], 'measure' => ['required', 'string', 'max:80'], 'target' => ['nullable', 'numeric', 'min:0', 'max:999999999999'], 'unit' => ['nullable', 'string', 'max:24'], 'filter_field' => ['nullable', 'string', 'max:80'], 'filter_value' => ['nullable', 'string', 'max:180']]);
        $source = $reports->sourceFor($request->user(), $data['dataset']);
        abort_unless(isset($source['measures'][$data['measure']]), 422, 'Choose an available KPI measure.');
        if ($data['dataset'] === 'invoices' && $data['measure'] === 'sum:total' && (($data['filter_field'] ?? null) !== 'currency' || ($data['filter_value'] ?? '') === '')) {
            throw ValidationException::withMessages(['measure' => 'Filter finance KPI totals to one currency before summing.']);
        }
        if (! empty($data['filter_field'])) abort_unless(isset($source['columns'][$data['filter_field']]), 422, 'Choose a filter field from the selected data source.');
        DB::table('bi_kpi_definitions')->insert(['organization_id' => $organizationId, 'name' => $data['name'], 'dataset' => $data['dataset'], 'measure' => $data['measure'], 'target' => $data['target'] ?? null, 'unit' => $data['unit'] ?? null, 'filter_field' => $data['filter_field'] ?? null, 'filter_value' => $data['filter_value'] ?? null, 'created_by' => $request->user()->id, 'created_at' => now(), 'updated_at' => now()]);
        return back()->with('success', 'KPI definition saved.');
    }

    public function deleteKpi(Request $request, int $kpi)
    {
        $organizationId = $this->organization($request); abort_unless($request->user()->isSuperAdmin() || $request->user()->can('reports.kpis.manage'), 403);
        DB::table('bi_kpi_definitions')->where('organization_id', $organizationId)->where('id', $kpi)->delete();
        return back()->with('success', 'KPI definition removed.');
    }

    public function storeSchedule(Request $request)
    {
        $organizationId = $this->organization($request); abort_unless($request->user()->isSuperAdmin() || $request->user()->can('reports.schedules.manage'), 403);
        $data = $request->validate(['report_id' => ['required', 'integer'], 'frequency' => ['required', Rule::in(['daily', 'weekly', 'monthly'])], 'recipient_user_ids' => ['required', 'string', 'max:1000']]);
        $report = DB::table('bi_custom_reports')->where('organization_id', $organizationId)->where('id', $data['report_id'])->first() ?: abort(404);
        abort_unless($this->canManage($request) || (int) $report->created_by === (int) $request->user()->id, 403);
        app(BusinessReports::class)->sourceFor($request->user(), $report->dataset);
        $ids = collect(preg_split('/\D+/', $data['recipient_user_ids']) ?: [])->filter()->map(fn ($id) => (int) $id)->unique()->take(10);
        $valid = DB::table('user_organization_memberships')->join('users', 'users.id', '=', 'user_organization_memberships.user_id')->where('user_organization_memberships.organization_id', $organizationId)->whereIn('user_organization_memberships.user_id', $ids)->where('users.status', 'active')->pluck('user_organization_memberships.user_id')->map(fn ($id) => (int) $id);
        abort_if($ids->isEmpty() || $valid->count() !== $ids->count(), 422, 'Choose active report recipients who belong to this organization.');
        $next = match ($data['frequency']) { 'daily' => now()->addDay()->startOfHour(), 'weekly' => now()->addWeek()->startOfHour(), default => now()->addMonth()->startOfHour() };
        DB::table('bi_report_schedules')->insert(['organization_id' => $organizationId, 'report_id' => $report->id, 'recipient_user_ids' => json_encode($valid->values(), JSON_THROW_ON_ERROR), 'frequency' => $data['frequency'], 'next_run_at' => $next, 'is_active' => true, 'created_by' => $request->user()->id, 'created_at' => now(), 'updated_at' => now()]);
        return back()->with('success', 'Delivery schedule configuration saved. Automated email delivery remains inactive.');
    }

    public function deleteSchedule(Request $request, int $schedule)
    {
        $organizationId = $this->organization($request); abort_unless($request->user()->isSuperAdmin() || $request->user()->can('reports.schedules.manage'), 403);
        DB::table('bi_report_schedules')->where('organization_id', $organizationId)->where('id', $schedule)->update(['is_active' => false, 'updated_at' => now()]);
        return back()->with('success', 'Delivery schedule configuration paused.');
    }
}
