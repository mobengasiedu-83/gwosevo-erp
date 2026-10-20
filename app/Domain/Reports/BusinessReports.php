<?php

namespace App\Domain\Reports;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class BusinessReports
{
    /** @return array<string,array{label:string,permission:string,table:string,date:string,columns:array<string,string>,groups:array<string,string>,measures:array<string,string>}> */
    public function catalog(): array
    {
        return [
            'leads' => ['label' => 'CRM leads', 'permission' => 'leads.reports.view', 'table' => 'leads', 'date' => 'created_at', 'columns' => ['reference' => 'Reference', 'status' => 'Status', 'source' => 'Source', 'budget' => 'Budget', 'created_at' => 'Created'], 'groups' => ['status' => 'Status', 'source' => 'Source'], 'measures' => ['count' => 'Count', 'sum:budget' => 'Budget']],
            'opportunities' => ['label' => 'Sales opportunities', 'permission' => 'opportunities.reports.view', 'table' => 'opportunities', 'date' => 'created_at', 'columns' => ['name' => 'Opportunity', 'stage' => 'Stage', 'amount' => 'Amount', 'probability' => 'Probability', 'expected_close_date' => 'Expected close'], 'groups' => ['stage' => 'Stage'], 'measures' => ['count' => 'Count', 'sum:amount' => 'Pipeline amount']],
            'calls' => ['label' => 'Contact centre calls', 'permission' => 'calls.view', 'table' => 'call_records', 'date' => 'started_at', 'columns' => ['direction' => 'Direction', 'status' => 'Status', 'queue' => 'Queue', 'wait_seconds' => 'Wait seconds', 'duration_seconds' => 'Duration seconds', 'started_at' => 'Started', 'disposition' => 'Disposition'], 'groups' => ['direction' => 'Direction', 'status' => 'Status', 'queue' => 'Queue'], 'measures' => ['count' => 'Count', 'avg:duration_seconds' => 'Average duration', 'avg:wait_seconds' => 'Average wait']],
            'tickets' => ['label' => 'Support tickets', 'permission' => 'tickets.reports', 'table' => 'tickets', 'date' => 'created_at', 'columns' => ['reference' => 'Reference', 'status' => 'Status', 'priority' => 'Priority', 'channel' => 'Channel', 'reopen_count' => 'Reopens', 'satisfaction_score' => 'Satisfaction', 'created_at' => 'Created'], 'groups' => ['status' => 'Status', 'priority' => 'Priority', 'channel' => 'Channel'], 'measures' => ['count' => 'Count', 'avg:reopen_count' => 'Average reopens', 'avg:satisfaction_score' => 'Average satisfaction']],
            'employees' => ['label' => 'Workforce', 'permission' => 'hr.reports.view', 'table' => 'hr_employees', 'date' => 'hire_date', 'columns' => ['employee_number' => 'Employee number', 'status' => 'Status', 'employment_type' => 'Employment type', 'hire_date' => 'Hire date', 'end_date' => 'End date', 'department_id' => 'Department ID'], 'groups' => ['status' => 'Status', 'employment_type' => 'Employment type', 'department_id' => 'Department'], 'measures' => ['count' => 'Headcount']],
            'leave' => ['label' => 'Leave requests', 'permission' => 'attendance.reports.view', 'table' => 'hr_leave_requests', 'date' => 'starts_on', 'columns' => ['status' => 'Status', 'starts_on' => 'Starts', 'ends_on' => 'Ends', 'requested_days' => 'Days requested', 'created_at' => 'Submitted'], 'groups' => ['status' => 'Status'], 'measures' => ['count' => 'Count', 'sum:requested_days' => 'Requested days']],
            'recruitment' => ['label' => 'Recruitment funnel', 'permission' => 'recruitment.reports.view', 'table' => 'hr_applications', 'date' => 'applied_at', 'columns' => ['stage' => 'Stage', 'applied_at' => 'Applied'], 'groups' => ['stage' => 'Stage'], 'measures' => ['count' => 'Applications']],
            'performance' => ['label' => 'Performance reviews', 'permission' => 'performance.reports.view', 'table' => 'hr_performance_reviews', 'date' => 'created_at', 'columns' => ['status' => 'Status', 'self_score' => 'Self score', 'manager_score' => 'Manager score', 'final_score' => 'Final score'], 'groups' => ['status' => 'Status'], 'measures' => ['count' => 'Reviews', 'avg:final_score' => 'Average final score']],
            'invoices' => ['label' => 'Finance receivables', 'permission' => 'finance.reports.view', 'table' => 'finance_invoices', 'date' => 'invoice_date', 'columns' => ['reference' => 'Reference', 'customer_name' => 'Customer', 'currency' => 'Currency', 'invoice_date' => 'Invoice date', 'due_date' => 'Due date', 'status' => 'Status', 'total' => 'Total', 'service_name' => 'Service', 'department_name' => 'Department'], 'groups' => ['status' => 'Status', 'currency' => 'Currency', 'service_name' => 'Service', 'department_name' => 'Department'], 'measures' => ['count' => 'Count', 'sum:total' => 'Invoice total']],
            'projects' => ['label' => 'Project delivery', 'permission' => 'projects.reports.view', 'table' => 'projects', 'date' => 'starts_on', 'columns' => ['name' => 'Project', 'status' => 'Status', 'client_name' => 'Client', 'starts_on' => 'Starts', 'ends_on' => 'Ends', 'budget' => 'Budget'], 'groups' => ['status' => 'Status'], 'measures' => ['count' => 'Count', 'sum:budget' => 'Budget']],
            'tasks' => ['label' => 'Task workload', 'permission' => 'projects.reports.view', 'table' => 'project_tasks', 'date' => 'created_at', 'columns' => ['title' => 'Task', 'status' => 'Status', 'priority' => 'Priority', 'due_on' => 'Due date', 'estimate_hours' => 'Estimate hours'], 'groups' => ['status' => 'Status', 'priority' => 'Priority'], 'measures' => ['count' => 'Count', 'sum:estimate_hours' => 'Estimated hours']],
        ];
    }

    public function available(User $user): array
    {
        return array_filter($this->catalog(), fn ($source) => $user->isSuperAdmin() || $user->can($source['permission']));
    }

    public function sourceFor(User $user, string $key): array
    {
        $sources = $this->available($user);
        if (! isset($sources[$key])) throw ValidationException::withMessages(['dataset' => 'You do not have access to this report data source.']);
        return $sources[$key];
    }

    public function rows(User $user, int $organizationId, array $definition): array
    {
        $source = $this->sourceFor($user, (string) ($definition['dataset'] ?? ''));
        if (! Schema::hasTable($source['table'])) return [];
        $table = $source['table'];
        $from = $definition['from'] ?? null;
        $to = $definition['to'] ?? null;
        $query = DB::table($table)->where('organization_id', $organizationId);
        if ($table === 'finance_invoices') $query->whereIn('status', ['issued', 'part_paid', 'paid', 'overdue']);
        if ($from) $query->whereDate($source['date'], '>=', $from);
        if ($to) $query->whereDate($source['date'], '<=', $to);
        if (! empty($definition['filter_field']) && array_key_exists('filter_value', $definition) && $definition['filter_value'] !== '') {
            $field = (string) $definition['filter_field'];
            if (! isset($source['columns'][$field])) throw ValidationException::withMessages(['filter_field' => 'Choose a field available in this report.']);
            $query->where($field, (string) $definition['filter_value']);
        }
        $group = $definition['group_by'] ?? '';
        $measure = $definition['measure'] ?? 'count';
        if ($group !== '') {
            if (! isset($source['groups'][$group])) throw ValidationException::withMessages(['group_by' => 'Choose a grouping available in this report.']);
            if (! isset($source['measures'][$measure])) throw ValidationException::withMessages(['measure' => 'Choose a measure available in this report.']);
            if ($table === 'finance_invoices' && $measure === 'sum:total' && $group !== 'currency' && (($definition['filter_field'] ?? null) !== 'currency' || ($definition['filter_value'] ?? '') === '')) {
                throw ValidationException::withMessages(['measure' => 'Group finance totals by currency or filter to one currency before summing.']);
            }
            $query->select($group)->groupBy($group)->orderBy($group);
            if ($measure === 'count') {
                $query->selectRaw('COUNT(*) as value');
            } else {
                [$aggregate, $field] = explode(':', $measure, 2);
                abort_unless(in_array($aggregate, ['sum', 'avg'], true) && isset($source['columns'][$field]), 422, 'Choose an available numeric measure.');
                $query->selectRaw(strtoupper($aggregate).'('.$field.') as value');
            }
            return $query->limit(500)->get()->map(fn ($row) => ['group' => $row->{$group} ?? 'Unspecified', 'value' => (float) ($row->value ?? 0)])->all();
        }
        $columns = collect($definition['columns'] ?? [])->filter(fn ($column) => isset($source['columns'][$column]))->unique()->take(12)->values()->all();
        if ($columns === []) $columns = array_slice(array_keys($source['columns']), 0, 5);
        return $query->select($columns)->orderByDesc($source['date'])->limit(1000)->get()->map(fn ($row) => (array) $row)->all();
    }

    public function aggregate(User $user, int $organizationId, array $definition): float
    {
        $source = $this->sourceFor($user, (string) ($definition['dataset'] ?? ''));
        if (! Schema::hasTable($source['table'])) return 0;
        $measure = (string) ($definition['measure'] ?? 'count');
        if (! isset($source['measures'][$measure])) throw ValidationException::withMessages(['measure' => 'Choose a measure available in this report.']);
        if ($source['table'] === 'finance_invoices' && $measure === 'sum:total' && (($definition['filter_field'] ?? null) !== 'currency' || ($definition['filter_value'] ?? '') === '')) {
            throw ValidationException::withMessages(['measure' => 'Filter finance totals to one currency before summing.']);
        }
        $query = DB::table($source['table'])->where('organization_id', $organizationId);
        if ($source['table'] === 'finance_invoices') $query->whereIn('status', ['issued', 'part_paid', 'paid', 'overdue']);
        if (! empty($definition['from'])) $query->whereDate($source['date'], '>=', $definition['from']);
        if (! empty($definition['to'])) $query->whereDate($source['date'], '<=', $definition['to']);
        if (! empty($definition['filter_field']) && array_key_exists('filter_value', $definition) && $definition['filter_value'] !== '') {
            $field = (string) $definition['filter_field'];
            if (! isset($source['columns'][$field])) throw ValidationException::withMessages(['filter_field' => 'Choose a field available in this report.']);
            $query->where($field, (string) $definition['filter_value']);
        }
        if ($measure === 'count') return (float) $query->count();
        [$aggregate, $field] = explode(':', $measure, 2);
        return (float) ($query->{$aggregate}($field) ?? 0);
    }

    public function csv(array $rows): string
    {
        $stream = fopen('php://temp', 'w+');
        if ($rows !== []) {
            fputcsv($stream, array_keys($rows[0]));
            foreach ($rows as $row) {
                $values = array_values($row);
                foreach ($values as &$value) {
                    if (is_string($value) && preg_match('/^[=+@\-\t\r]/', $value)) $value = "'".$value;
                }
                unset($value);
                fputcsv($stream, $values);
            }
        }
        rewind($stream);
        $csv = stream_get_contents($stream) ?: '';
        fclose($stream);
        return $csv;
    }
}
