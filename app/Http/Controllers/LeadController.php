<?php

namespace App\Http\Controllers;

use App\Domain\Administration\CustomFields;
use App\Domain\Administration\Settings;
use App\Domain\CRM\LeadDuplicates;
use App\Domain\CRM\LeadService;
use App\Domain\Identity\LeadAccess;
use App\Models\CustomField;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class LeadController extends Controller
{
    public function index(Request $r)
    {
        $this->authorize('viewAny', Lead::class);
        $query = app(LeadAccess::class)->query($r->user())->with('owner')->when($r->boolean('include_archived'), function ($q) use ($r) { abort_unless($r->user()->can('leads.archive'), 403); return $q->whereNotNull('archived_at'); }, fn ($q) => $q->whereNull('archived_at'))
            ->when($r->filled('status'), fn ($q) => $q->where('status', $r->string('status')))
            ->when($r->filled('source'), fn ($q) => $q->where('source', $r->string('source')))
            ->when($r->filled('owner_id'), fn ($q) => $q->where('owner_id', $r->integer('owner_id')))
            ->when($r->filled('search'), fn ($q) => $q->where(fn ($x) => $x->where('first_name', 'like', '%'.$r->search.'%')->orWhere('last_name', 'like', '%'.$r->search.'%')->orWhere('company_name', 'like', '%'.$r->search.'%')->orWhere('email', 'like', '%'.$r->search.'%')->orWhere('phone', 'like', '%'.$r->search.'%')));
        $leads = $query->latest()->paginate(app(Settings::class)->get('pagination'))->withQueryString();
        $sources = $this->sourceOptions($r);

        return view('leads.index', compact('leads', 'sources'));
    }

    public function create()
    {
        $this->authorize('create', Lead::class);

        return view('leads.form', ['lead' => new Lead, 'customFields' => $this->customFields(), 'duplicates' => collect(), 'sources' => $this->sourceOptions(request())]);
    }

    public function store(Request $r, LeadService $s)
    {
        $this->authorize('create', Lead::class);
        $lead = $s->create([...$this->data($r), 'organization_id' => $r->user()->active_organization_id], $r->user());

        return redirect()->route('leads.show', $lead)->with('success', 'Lead captured successfully.');
    }

    public function show(Lead $lead)
    {
        $this->authorize('view', $lead);
        $lead->load(['owner', 'activities.creator', 'statusHistory.actor', 'account', 'contact', 'opportunity', 'possibleDuplicateOf']);
        if ($lead->possibleDuplicateOf && ! app(LeadAccess::class)->allows(request()->user(), $lead->possibleDuplicateOf)) $lead->setRelation('possibleDuplicateOf', null);
        $duplicates = $lead->duplicate_reviewed_at && ! $lead->duplicate_of_id ? collect() : app(LeadDuplicates::class)->candidates($lead, 5, request()->user());

        return view('leads.show', ['lead' => $lead, 'duplicates' => $duplicates, 'owners' => $this->owners($lead->organization_id), 'customFields' => $this->customFields()]);
    }

    public function edit(Lead $lead)
    {
        $this->authorize('update', $lead);

        return view('leads.form', ['lead' => $lead, 'customFields' => $this->customFields(), 'duplicates' => $lead->duplicate_reviewed_at && ! $lead->duplicate_of_id ? collect() : app(LeadDuplicates::class)->candidates($lead, 5, request()->user()), 'sources' => $this->sourceOptions(request())]);
    }

    public function update(Request $r, Lead $lead, LeadService $service)
    {
        $this->authorize('update', $lead);
        $data = $this->data($r);
        $data['custom_values'] = array_replace($lead->custom_values ?? [], $data['custom_values']);
        $service->update($lead, $data, $r->user());

        return redirect()->route('leads.show', $lead)->with('success', 'Lead updated.');
    }

    public function assign(Request $r, Lead $lead, LeadService $s)
    {
        $this->authorize('assign', $lead);
        $d = $r->validate(['owner_id' => ['required', 'integer', Rule::exists('users', 'id')]]);
        $s->assign($lead, User::findOrFail($d['owner_id']), $r->user(), 'Manually assigned');

        return back()->with('success', 'Lead owner updated.');
    }

    public function qualify(Request $r, Lead $lead, LeadService $s)
    {
        $this->authorize('qualify', $lead);
        $d = $r->validate(['budget' => ['nullable', 'numeric', 'min:0'], 'authority' => ['nullable', 'string', 'max:255'], 'need' => ['nullable', 'string', 'max:5000'], 'timeline' => ['nullable', 'string', 'max:255']]);
        $s->qualify($lead, $d, $r->user());

        return back()->with('success', 'Lead qualified. Fit and engagement scores were recalculated.');
    }

    public function convert(Lead $lead, LeadService $s)
    {
        $this->authorize('convert', $lead);
        $s->convert($lead, request()->user());

        return back()->with('success', 'Lead converted to account, contact and opportunity.');
    }

    public function disqualify(Request $r, Lead $lead, LeadService $s)
    {
        $this->authorize('disqualify', $lead);
        $data = $r->validate(['reason' => ['required', 'string', 'max:1000']]);
        $s->disqualify($lead, $data['reason'], $r->user());

        return back()->with('success', 'Lead disqualified.');
    }

    public function activity(Request $r, Lead $lead, LeadService $s)
    {
        $this->authorize('update', $lead);
        $d = $r->validate(['type' => ['required', Rule::in(['call', 'email', 'meeting', 'note', 'task'])], 'subject' => ['required', 'string', 'max:255'], 'body' => ['nullable', 'string', 'max:10000'], 'due_at' => ['nullable', 'date']]);
        $s->addActivity($lead, $d, $r->user());

        return back()->with('success', 'Activity added.');
    }

    public function completeActivity(Lead $lead, int $activity, LeadService $s)
    {
        $this->authorize('update', $lead);
        $s->completeActivity($lead, $activity, request()->user());

        return back()->with('success', 'Follow-up marked complete.');
    }

    public function duplicateReview(Request $r, Lead $lead, LeadDuplicates $duplicates)
    {
        $this->authorize('reviewDuplicates', $lead);
        $data = $r->validate(['is_duplicate' => ['required', 'boolean'], 'canonical_id' => ['nullable', 'integer', Rule::exists('leads', 'id')->where('organization_id', $lead->organization_id)]]);
        $canonical = ! empty($data['canonical_id']) ? Lead::where('organization_id', $lead->organization_id)->whereNull('archived_at')->findOrFail($data['canonical_id']) : null;
        if ($canonical && ! app(LeadAccess::class)->allows($r->user(), $canonical)) return back()->withErrors(['canonical_id' => 'Choose a lead you are permitted to access.']);
        if ($data['is_duplicate'] && (! $canonical || $canonical->is($lead))) return back()->withErrors(['canonical_id' => 'Select a different canonical lead.']);
        $duplicates->markReviewed($lead, (bool) $data['is_duplicate'], $canonical, $r->user()->id);

        return back()->with('success', $data['is_duplicate'] ? 'Duplicate relationship recorded.' : 'Lead marked as a unique record.');
    }

    public function archive(Lead $lead, LeadService $service)
    {
        $this->authorize('archive', $lead);
        $service->archive($lead, request()->user());

        return redirect()->route('leads.index')->with('success', 'Lead archived.');
    }

    public function restore(Lead $lead, LeadService $service)
    {
        $this->authorize('archive', $lead);
        $service->restore($lead, request()->user());

        return redirect()->route('leads.show', $lead)->with('success', 'Lead restored.');
    }

    public function importPage()
    {
        $this->authorize('import', Lead::class);

        return view('leads.import');
    }

    public function importPreview(Request $r)
    {
        $this->authorize('import', Lead::class);
        $r->validate(['file' => ['required', 'file', 'mimes:csv,txt', 'max:4096']]);
        $handle = fopen($r->file('file')->getRealPath(), 'r');
        session()->forget('leads.import.preview');
        $headers = array_map(fn ($h) => trim(mb_strtolower(str_replace("\xEF\xBB\xBF", '', (string) $h))), fgetcsv($handle) ?: []);
        abort_if(empty($headers) || count($headers) !== count(array_unique($headers)) || in_array('', $headers, true), 422, 'CSV headings must be nonempty and unique.');
        abort_if(array_diff($headers, ['first_name','last_name','email','phone','company_name','industry','interests']), 422, 'CSV contains unsupported columns.');
        $required = ['first_name', 'last_name'];
        abort_if(array_diff($required, $headers), 422, 'CSV must include first_name and last_name columns.');
        $rows = $rowErrors = [];
        $line = 1; $tooManyRows = false;
        while (($values = fgetcsv($handle)) !== false) {
            if (++$line > 1001) { $tooManyRows = true; break; }
            if (count($values) > count($headers)) { $rowErrors[] = ['row' => $line, 'errors' => ['Too many values for this row.']]; continue; }
            $row = array_combine($headers, array_pad($values, count($headers), null));
            $validator = Validator::make($row, ['first_name' => ['required', 'string', 'max:100'], 'last_name' => ['required', 'string', 'max:100'], 'email' => ['nullable', 'email', 'max:255'], 'phone' => ['nullable', 'string', 'max:50'], 'company_name' => ['nullable', 'string', 'max:255'], 'industry' => ['nullable', 'string', 'max:255'], 'interests' => ['nullable', 'string', 'max:5000']]);
            if ($validator->fails()) $rowErrors[] = ['row' => $line, 'errors' => $validator->errors()->all()];
            else $rows[] = $validator->validated();
        }
        fclose($handle);
        abort_if($tooManyRows, 422, 'CSV import is limited to 1,000 data rows.');
        session(['leads.import.preview' => ['organization_id' => auth()->user()->active_organization_id, 'rows' => $rows]]);

        return view('leads.import', compact('rows', 'rowErrors'));
    }

    public function importCommit(Request $r, LeadService $service)
    {
        $this->authorize('import', Lead::class);
        $preview = session()->pull('leads.import.preview', []);
        abort_if(empty($preview['rows']), 419, 'Import preview expired. Upload the file again.');
        abort_unless((int) ($preview['organization_id'] ?? 0) === (int) $r->user()->active_organization_id, 409, 'Active organization changed. Upload and preview the file again.');
        $rows = $preview['rows'];
        \Illuminate\Support\Facades\DB::transaction(function () use ($rows, $service, $r, &$created) {
            $created = 0;
            foreach ($rows as $row) {
                $service->create([...$row, 'source' => 'import', 'organization_id' => $r->user()->active_organization_id], $r->user());
                $created++;
            }
        });

        return redirect()->route('leads.index')->with('success', "Imported {$created} leads. Possible duplicates are flagged for review.");
    }

    public function report(Request $r, LeadAccess $access)
    {
        $this->authorize('viewReports', Lead::class);
        if ($r->query('export') === 'csv') $this->authorize('export', Lead::class);
        $base = $access->query($r->user())->whereNull('archived_at');
        $bySource = (clone $base)->selectRaw('source, COUNT(*) AS total')->groupBy('source')->orderByDesc('total')->pluck('total', 'source');
        $byStatus = (clone $base)->selectRaw('status, COUNT(*) AS total')->groupBy('status')->orderByDesc('total')->pluck('total', 'status');
        $ownerRows = (clone $base)->selectRaw('owner_id, COUNT(*) AS total, SUM(CASE WHEN qualified_at IS NOT NULL THEN 1 ELSE 0 END) AS qualified, SUM(CASE WHEN converted_at IS NOT NULL THEN 1 ELSE 0 END) AS converted')->groupBy('owner_id')->get();
        $byOwner = $ownerRows->mapWithKeys(fn ($row) => [$row->owner_id ?: 'unassigned' => ['total' => (int) $row->total, 'qualified' => (int) $row->qualified, 'converted' => (int) $row->converted]]);
        $total = (clone $base)->count();
        $converted = (clone $base)->whereNotNull('converted_at')->count();
        $report = [
            'bySource' => $bySource, 'byStatus' => $byStatus, 'byOwner' => $byOwner,
            'ageing' => [
                '0-7 days' => (clone $base)->where('created_at', '>=', now()->subDays(7))->count(),
                '8-30 days' => (clone $base)->where('created_at', '<', now()->subDays(7))->where('created_at', '>=', now()->subDays(30))->count(),
                'Over 30 days' => (clone $base)->where('created_at', '<', now()->subDays(30))->count(),
            ],
            'conversionRate' => $total ? round($converted / $total * 100, 1) : 0,
        ];
        if ($r->query('export') === 'csv') {
            return response()->streamDownload(function () use ($report) {
                $out = fopen('php://output', 'w'); fputcsv($out, ['Group', 'Value', 'Total', 'Qualified', 'Converted']);
                foreach ($report['bySource'] as $key => $value) fputcsv($out, ['Source: '.$key, $value, '', '', '']);
                foreach ($report['byStatus'] as $key => $value) fputcsv($out, ['Status: '.$key, $value, '', '', '']);
                foreach ($report['byOwner'] as $key => $value) fputcsv($out, ['Owner: '.$key, '', $value['total'], $value['qualified'], $value['converted']]);
                foreach ($report['ageing'] as $key => $value) fputcsv($out, ['Age: '.$key, $value, '', '', '']);
                fputcsv($out, ['Conversion rate', $report['conversionRate'].'%']); fclose($out);
            }, 'crm-lead-report.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
        }

        return view('leads.report', compact('report'));
    }

    private function customFields()
    {
        return CustomField::where('record_type', 'lead')->where('is_active', true)->get();
    }

    private function owners(int $organizationId)
    {
        return User::where('status', 'active')->whereHas('organizations', fn ($q) => $q->where('organizations.id', $organizationId))->orderBy('name')->get()->filter(fn (User $user) => $user->can('leads.view'))->values();
    }

    private function data(Request $r): array
    {
        $sources = array_keys($this->sourceOptions($r));
        $data = $r->validate(['first_name' => ['required', 'string', 'max:100'], 'last_name' => ['required', 'string', 'max:100'], 'email' => ['nullable', 'email', 'max:255'], 'phone' => ['nullable', 'string', 'max:50'], 'company_name' => ['nullable', 'string', 'max:255'], 'industry' => ['nullable', 'string', 'max:255'], 'interests' => ['nullable', 'string', 'max:5000'], 'source' => ['required', Rule::in($sources)], 'source_detail' => ['nullable', 'string', 'max:255'], 'next_follow_up_at' => ['nullable', 'date'], 'custom_values' => ['nullable', 'array']]);
        $data['custom_values'] = app(CustomFields::class)->validate($data['custom_values'] ?? []);

        return $data;
    }

    private function sourceOptions(Request $request): array
    {
        $configured = config('crm.sources', []);
        if (! $request->user()?->active_organization_id || ! DB::getSchemaBuilder()->hasTable('crm_lead_sources')) return $configured;
        $custom = DB::table('crm_lead_sources')->where('organization_id', $request->user()->active_organization_id)->where('is_active', true)->pluck('name', 'key')->all();
        return $configured + $custom;
    }
}
