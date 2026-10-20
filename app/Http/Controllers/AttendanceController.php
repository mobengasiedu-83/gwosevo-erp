<?php

namespace App\Http\Controllers;

use App\Domain\Administration\AdminAudit;
use App\Domain\Administration\Modules;
use App\Models\HrEmployee;
use App\Models\Organization;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AttendanceController extends Controller
{
    private function enabled(): void { abort_unless(app(Modules::class)->enabled('attendance') && app(Modules::class)->enabled('hr'), 404); }

    private function organization(Request $request): Organization
    {
        $id = (int) ($request->input('organization_id') ?: $request->query('organization_id') ?: $request->user()->active_organization_id ?: $request->user()->organizations()->orderBy('organizations.name')->value('organizations.id'));
        abort_unless($id && $request->user()->canAccessOrganization($id), 403);
        $org = Organization::findOrFail($id); abort_unless($org->is_active || $request->user()->isSuperAdmin(), 403);
        return $org;
    }

    private function selfEmployee(Request $request, Organization $org): HrEmployee
    {
        return HrEmployee::where('organization_id', $org->id)->where('user_id', $request->user()->id)->where('status', 'active')->firstOrFail();
    }

    private function canReviewEmployee(Request $request, HrEmployee $employee): bool
    {
        if ($request->user()->isSuperAdmin() || $request->user()->can('attendance.manage') || $request->user()->can('attendance.approve')) return true;
        return $request->user()->can('attendance.view') && $employee->manager_employee_id && (int) HrEmployee::whereKey($employee->manager_employee_id)->value('user_id') === (int) $request->user()->id;
    }

    public function index(Request $request)
    {
        $this->enabled(); abort_unless($request->user()->can('attendance.view') || $request->user()->can('attendance.self'), 403); $org = $this->organization($request);
        $employee = HrEmployee::where('organization_id', $org->id)->where('user_id', $request->user()->id)->first();
        $self = $request->user()->can('attendance.self') && $employee;
        $full = $request->user()->isSuperAdmin() || $request->user()->can('attendance.manage') || $request->user()->can('attendance.approve');
        $scopeEmployeeIds = null;
        if (! $full) {
            $scopeEmployeeIds = collect($self ? [$employee->id] : []);
            if ($request->user()->can('attendance.view') && $employee) $scopeEmployeeIds = $scopeEmployeeIds->merge(HrEmployee::where('organization_id', $org->id)->where('manager_employee_id', $employee->id)->pluck('id'));
            $scopeEmployeeIds = $scopeEmployeeIds->unique()->values()->all();
        }
        $recordsQuery = DB::table('hr_attendance_records as a')->join('hr_employees as e', 'e.id', '=', 'a.employee_id')->where('a.organization_id', $org->id);
        $leaveQuery = DB::table('hr_leave_requests as l')->join('hr_employees as e', 'e.id', '=', 'l.employee_id')->where('l.organization_id', $org->id);
        $timesheetQuery = DB::table('hr_timesheets as t')->join('hr_employees as e', 'e.id', '=', 't.employee_id')->where('t.organization_id', $org->id);
        if ($scopeEmployeeIds !== null) { $recordsQuery->whereIn('a.employee_id', $scopeEmployeeIds); $leaveQuery->whereIn('l.employee_id', $scopeEmployeeIds); }
        if ($scopeEmployeeIds !== null && ! $request->user()->can('attendance.timesheets.manage')) $timesheetQuery->whereIn('t.employee_id', $scopeEmployeeIds);
        $records = $recordsQuery->select('a.*', 'e.first_name', 'e.last_name', 'e.employee_number')->orderByDesc('a.work_date')->limit(60)->get();
        $leaveRequests = $leaveQuery->select('l.*', 'e.first_name', 'e.last_name', 'e.employee_number')->orderByDesc('l.created_at')->limit(60)->get();
        $timesheets = $timesheetQuery->select('t.*', 'e.first_name', 'e.last_name')->orderByDesc('t.work_date')->limit(60)->get();
        $types = DB::table('hr_leave_types')->where('organization_id', $org->id)->where('is_active', true)->orderBy('name')->get();
        $balances = $employee ? $types->map(function ($type) use ($employee, $org) {
            $balance = DB::table('hr_leave_balances')->where('organization_id', $org->id)->where('employee_id', $employee->id)->where('leave_type_id', $type->id)->where('year', now()->year)->first();
            $type->leave_type_id = $type->id;
            $type->type_name = $type->name;
            $type->entitlement_days = $balance?->entitlement_days ?? $type->default_entitlement;
            $type->carryover_days = $balance?->carryover_days ?? 0;
            $type->adjustment_days = $balance?->adjustment_days ?? 0;
            $type->used_days = (float) DB::table('hr_leave_requests')->where('employee_id', $employee->id)->where('leave_type_id', $type->id)->whereYear('starts_on', now()->year)->where('status', 'approved')->sum('requested_days');
            $type->available_days = (float) $type->entitlement_days + (float) $type->carryover_days + (float) $type->adjustment_days - $type->used_days;
            return $type;
        }) : collect();
        $shifts = DB::table('hr_shifts')->where('organization_id', $org->id)->where('is_active', true)->orderBy('name')->get();
        $holidays = DB::table('hr_holidays')->where('organization_id', $org->id)->whereBetween('holiday_on', [today()->startOfYear(), today()->endOfYear()])->orderBy('holiday_on')->get();
        $todayRecord = $employee ? DB::table('hr_attendance_records')->where('employee_id', $employee->id)->where('work_date', today())->first() : null;
        $pendingLeave = $leaveRequests->where('status', 'pending')->count(); $pendingTimesheets = $timesheets->where('status', 'submitted')->count();
        $reports = null;
        if ($request->user()->can('attendance.reports.view')) {
            $reports = ['present' => DB::table('hr_attendance_records')->where('organization_id', $org->id)->whereBetween('work_date', [today()->startOfMonth(), today()->endOfMonth()])->where('status', 'present')->count(), 'late' => DB::table('hr_attendance_records')->where('organization_id', $org->id)->whereBetween('work_date', [today()->startOfMonth(), today()->endOfMonth()])->where('status', 'late')->count(), 'absent' => DB::table('hr_attendance_records')->where('organization_id', $org->id)->whereBetween('work_date', [today()->startOfMonth(), today()->endOfMonth()])->where('status', 'absent')->count(), 'leave_days' => (float) DB::table('hr_leave_requests')->where('organization_id', $org->id)->where('status', 'approved')->whereBetween('starts_on', [today()->startOfMonth(), today()->endOfMonth()])->sum('requested_days')];
        }
        $employees = ($request->user()->can('attendance.manage') || $request->user()->can('attendance.balances.manage') || $request->user()->can('attendance.shifts.manage')) ? HrEmployee::where('organization_id', $org->id)->where('status', 'active')->orderBy('last_name')->get() : collect();
        $trainingCourses = DB::table('hr_training_courses')->where('organization_id', $org->id)->where('is_active', true)->orderBy('name')->get();
        return view('attendance.index', compact('org', 'employee', 'self', 'full', 'records', 'leaveRequests', 'timesheets', 'types', 'balances', 'shifts', 'holidays', 'todayRecord', 'pendingLeave', 'pendingTimesheets', 'reports', 'employees', 'trainingCourses'));
    }

    public function clockIn(Request $request)
    {
        $this->enabled(); abort_unless($request->user()->can('attendance.clock'), 403); $org = $this->organization($request); $employee = $this->selfEmployee($request, $org);
        $existing = DB::table('hr_attendance_records')->where('employee_id', $employee->id)->where('work_date', today())->first(); abort_if($existing?->check_in_at, 422, 'You are already clocked in today.');
        $shift = $employee->shift_id ? DB::table('hr_shifts')->where('organization_id', $org->id)->where('id', $employee->shift_id)->first() : null;
        $status = 'present';
        if ($shift) { $weekday = (int) today()->isoWeekday(); $scheduled = json_decode($shift->weekdays, true) ?: []; if (! in_array($weekday, $scheduled, true)) $status = 'exception'; else { $due = today()->setTimeFromTimeString($shift->starts_at)->addMinutes($shift->grace_minutes); if (now()->greaterThan($due)) $status = 'late'; } }
        $id = DB::table('hr_attendance_records')->updateOrInsert(['employee_id' => $employee->id, 'work_date' => today()->toDateString()], ['organization_id' => $org->id, 'shift_id' => $shift?->id, 'check_in_at' => now(), 'status' => $status, 'source' => 'self_service', 'updated_at' => now(), 'created_at' => $existing?->created_at ?? now()]);
        AdminAudit::record('attendance.clocked_in', 'hr_attendance_records', (int) (DB::table('hr_attendance_records')->where('employee_id', $employee->id)->where('work_date', today())->value('id')), null, ['status' => $status, 'at' => now()->toIso8601String()], $org->id);
        return back()->with('success', 'Clock-in recorded at '.now()->format('H:i').'.');
    }

    public function clockOut(Request $request)
    {
        $this->enabled(); abort_unless($request->user()->can('attendance.clock'), 403); $org = $this->organization($request); $employee = $this->selfEmployee($request, $org);
        $record = DB::table('hr_attendance_records')->where('organization_id', $org->id)->where('employee_id', $employee->id)->where('work_date', today())->first(); abort_unless($record?->check_in_at, 422, 'Clock in before clocking out.'); abort_if($record->check_out_at, 422, 'You are already clocked out.');
        DB::table('hr_attendance_records')->where('id', $record->id)->update(['check_out_at' => now(), 'updated_at' => now()]);
        AdminAudit::record('attendance.clocked_out', 'hr_attendance_records', $record->id, null, ['at' => now()->toIso8601String()], $org->id);
        return back()->with('success', 'Clock-out recorded at '.now()->format('H:i').'.');
    }

    public function recordStore(Request $request)
    {
        $this->enabled(); abort_unless($request->user()->can('attendance.manage'), 403); $org = $this->organization($request);
        $data = $request->validate(['employee_id' => ['required', 'integer'], 'work_date' => ['required', 'date'], 'check_in_at' => ['nullable', 'date'], 'check_out_at' => ['nullable', 'date', 'after_or_equal:check_in_at'], 'status' => ['required', Rule::in(['present', 'late', 'absent', 'exception'])], 'exception_note' => ['nullable', 'string', 'max:2000']]);
        $employee = HrEmployee::where('organization_id', $org->id)->whereKey($data['employee_id'])->firstOrFail();
        $prior = DB::table('hr_attendance_records')->where('employee_id', $employee->id)->where('work_date', $data['work_date'])->first();
        $id = DB::table('hr_attendance_records')->updateOrInsert(['employee_id' => $employee->id, 'work_date' => $data['work_date']], [...$data, 'organization_id' => $org->id, 'source' => 'manual', 'reviewed_by' => $request->user()->id, 'updated_at' => now(), 'created_at' => $prior?->created_at ?? now()]);
        $recordId = DB::table('hr_attendance_records')->where('employee_id', $employee->id)->where('work_date', $data['work_date'])->value('id');
        AdminAudit::record('attendance.record.adjusted', 'hr_attendance_records', (int) $recordId, $prior ? ['status' => $prior->status] : null, ['status' => $data['status'], 'source' => 'manual'], $org->id);
        return back()->with('success', 'Attendance record saved.');
    }

    public function shiftStore(Request $request)
    {
        $this->enabled(); abort_unless($request->user()->can('attendance.shifts.manage'), 403); $org = $this->organization($request);
        $data = $request->validate(['name' => ['required', 'string', 'max:120', Rule::unique('hr_shifts')->where('organization_id', $org->id)], 'starts_at' => ['required', 'date_format:H:i'], 'ends_at' => ['required', 'date_format:H:i', 'different:starts_at'], 'grace_minutes' => ['required', 'integer', 'min:0', 'max:240'], 'weekdays' => ['required', 'array', 'min:1'], 'weekdays.*' => ['integer', 'between:1,7'], 'employee_id' => ['nullable', 'integer']]);
        $id = DB::table('hr_shifts')->insertGetId(['organization_id' => $org->id, 'name' => $data['name'], 'starts_at' => $data['starts_at'], 'ends_at' => $data['ends_at'], 'grace_minutes' => $data['grace_minutes'], 'weekdays' => json_encode(array_values(array_unique($data['weekdays']))), 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        if (!empty($data['employee_id'])) HrEmployee::where('organization_id', $org->id)->whereKey($data['employee_id'])->update(['shift_id' => $id]);
        AdminAudit::record('attendance.shift.created', 'hr_shifts', $id, null, ['name' => $data['name'], 'employee_id' => $data['employee_id'] ?? null], $org->id);
        return back()->with('success', 'Shift schedule created.'.(!empty($data['employee_id']) ? ' Assigned to the employee.' : ' Assign it on the employee record if required.'));
    }

    public function leaveTypeStore(Request $request)
    {
        $this->enabled(); abort_unless($request->user()->can('attendance.manage'), 403); $org = $this->organization($request);
        $data = $request->validate(['name' => ['required', 'string', 'max:120'], 'code' => ['required', 'alpha_dash', 'max:30', Rule::unique('hr_leave_types')->where('organization_id', $org->id)], 'default_entitlement' => ['required', 'numeric', 'min:0', 'max:366'], 'requires_approval' => ['required', 'boolean'], 'is_paid' => ['required', 'boolean']]);
        $id = DB::table('hr_leave_types')->insertGetId([...$data, 'organization_id' => $org->id, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        AdminAudit::record('attendance.leave_type.created', 'hr_leave_types', $id, null, ['name' => $data['name'], 'code' => $data['code']], $org->id);
        return back()->with('success', 'Leave type created. Set balances separately for each employee and year.');
    }

    public function leaveBalanceStore(Request $request)
    {
        $this->enabled(); abort_unless($request->user()->can('attendance.balances.manage'), 403); $org = $this->organization($request);
        $data = $request->validate(['employee_id' => ['required', 'integer'], 'leave_type_id' => ['required', 'integer'], 'year' => ['required', 'integer', 'min:2020', 'max:2100'], 'entitlement_days' => ['required', 'numeric', 'min:0', 'max:366'], 'carryover_days' => ['required', 'numeric', 'min:0', 'max:366'], 'adjustment_days' => ['required', 'numeric', 'between:-366,366']]);
        abort_unless(HrEmployee::where('organization_id', $org->id)->whereKey($data['employee_id'])->exists() && DB::table('hr_leave_types')->where('organization_id', $org->id)->whereKey($data['leave_type_id'])->exists(), 422);
        $existing = DB::table('hr_leave_balances')->where('employee_id', $data['employee_id'])->where('leave_type_id', $data['leave_type_id'])->where('year', $data['year'])->first();
        DB::table('hr_leave_balances')->updateOrInsert(['employee_id' => $data['employee_id'], 'leave_type_id' => $data['leave_type_id'], 'year' => $data['year']], [...$data, 'organization_id' => $org->id, 'updated_by' => $request->user()->id, 'updated_at' => now(), 'created_at' => $existing?->created_at ?? now()]);
        AdminAudit::record('attendance.leave_balance.updated', 'hr_leave_balances', (int) DB::table('hr_leave_balances')->where('employee_id', $data['employee_id'])->where('leave_type_id', $data['leave_type_id'])->where('year', $data['year'])->value('id'), null, ['entitlement_days' => $data['entitlement_days'], 'carryover_days' => $data['carryover_days']], $org->id);
        return back()->with('success', 'Leave balance updated. Approved requests reduce the available days.');
    }

    private function businessDays(Organization $org, string $start, string $end): float
    {
        $holidays = DB::table('hr_holidays')->where('organization_id', $org->id)->whereBetween('holiday_on', [$start, $end])->pluck('holiday_on')->map(fn ($d) => (string) $d)->all();
        $days = 0; $date = Carbon::parse($start)->startOfDay(); $last = Carbon::parse($end)->startOfDay();
        while ($date->lte($last)) { if ($date->isWeekday() && ! in_array($date->toDateString(), $holidays, true)) $days++; $date->addDay(); }
        return (float) $days;
    }

    public function leaveRequestStore(Request $request)
    {
        $this->enabled(); abort_unless($request->user()->can('attendance.self'), 403); $org = $this->organization($request); $employee = $this->selfEmployee($request, $org);
        $data = $request->validate(['leave_type_id' => ['required', 'integer'], 'starts_on' => ['required', 'date', 'after_or_equal:today'], 'ends_on' => ['required', 'date', 'after_or_equal:starts_on'], 'reason' => ['nullable', 'string', 'max:3000']]);
        abort_unless(Carbon::parse($data['starts_on'])->year === Carbon::parse($data['ends_on'])->year, 422, 'A leave request must fall within one calendar year.');
        $type = DB::table('hr_leave_types')->where('organization_id', $org->id)->where('is_active', true)->find($data['leave_type_id']); abort_unless($type, 422);
        $days = $this->businessDays($org, $data['starts_on'], $data['ends_on']); abort_if($days <= 0, 422, 'The selected dates contain no working days.');
        $overlap = DB::table('hr_leave_requests')->where('employee_id', $employee->id)->whereIn('status', ['pending', 'approved'])->whereDate('starts_on', '<=', $data['ends_on'])->whereDate('ends_on', '>=', $data['starts_on'])->exists(); abort_if($overlap, 422, 'This request overlaps another pending or approved leave request.');
        $year = Carbon::parse($data['starts_on'])->year;
        $balance = DB::table('hr_leave_balances')->where('organization_id', $org->id)->where('employee_id', $employee->id)->where('leave_type_id', $type->id)->where('year', $year)->first();
        $used = (float) DB::table('hr_leave_requests')->where('employee_id', $employee->id)->where('leave_type_id', $type->id)->whereYear('starts_on', $year)->where('status', 'approved')->sum('requested_days');
        $available = $balance ? (float) $balance->entitlement_days + (float) $balance->carryover_days + (float) $balance->adjustment_days - $used : (float) $type->default_entitlement - $used;
        abort_if($days > $available, 422, 'Insufficient leave balance. Available: '.max($available, 0).' days.');
        $status = $type->requires_approval ? 'pending' : 'approved';
        $id = DB::table('hr_leave_requests')->insertGetId(['organization_id' => $org->id, 'employee_id' => $employee->id, 'leave_type_id' => $type->id, 'starts_on' => $data['starts_on'], 'ends_on' => $data['ends_on'], 'requested_days' => $days, 'reason' => !empty($data['reason']) ? Crypt::encryptString($data['reason']) : null, 'status' => $status, 'decided_at' => $type->requires_approval ? null : now(), 'created_at' => now(), 'updated_at' => now()]);
        AdminAudit::record('attendance.leave.requested', 'hr_leave_requests', $id, null, ['leave_type_id' => $type->id, 'days' => $days, 'status' => $status], $org->id);
        return back()->with('success', 'Leave request recorded ('.$days.' working days).'.($type->requires_approval ? ' It is awaiting approval.' : ' It was approved under this leave type policy.'));
    }

    public function leaveRequestUpdate(Request $request, int $leaveRequest)
    {
        $this->enabled(); $org = $this->organization($request); $item = DB::table('hr_leave_requests')->where('organization_id', $org->id)->find($leaveRequest); abort_unless($item, 404); $employee = HrEmployee::where('organization_id', $org->id)->findOrFail($item->employee_id);
        if ($request->input('action') === 'cancel') {
            abort_unless((int) $employee->user_id === (int) $request->user()->id && $request->user()->can('attendance.self'), 403); abort_unless($item->status === 'pending', 422, 'Only pending requests can be cancelled.');
            DB::table('hr_leave_requests')->where('id', $item->id)->update(['status' => 'cancelled', 'updated_at' => now()]);
            AdminAudit::record('attendance.leave.cancelled', 'hr_leave_requests', $item->id, ['status' => 'pending'], ['status' => 'cancelled'], $org->id);
            return back()->with('success', 'Leave request cancelled.');
        }
        abort_unless($this->canReviewEmployee($request, $employee), 403); abort_unless($item->status === 'pending', 422, 'Only pending requests can be decided.');
        abort_unless((int) $employee->user_id !== (int) $request->user()->id, 403, 'You cannot approve your own leave request.');
        $data = $request->validate(['action' => ['required', Rule::in(['approved', 'rejected'])], 'decision_note' => ['nullable', 'string', 'max:2000']]);
        DB::table('hr_leave_requests')->where('id', $item->id)->update(['status' => $data['action'], 'decision_note' => $data['decision_note'] ?? null, 'decided_by' => $request->user()->id, 'decided_at' => now(), 'updated_at' => now()]);
        AdminAudit::record('attendance.leave.decided', 'hr_leave_requests', $item->id, ['status' => 'pending'], ['status' => $data['action']], $org->id);
        return back()->with('success', 'Leave request '.$data['action'].'.');
    }

    public function holidayStore(Request $request)
    {
        $this->enabled(); abort_unless($request->user()->can('attendance.manage'), 403); $org = $this->organization($request);
        $data = $request->validate(['holiday_on' => ['required', 'date'], 'name' => ['required', 'string', 'max:180'], 'scope' => ['required', Rule::in(['ghana', 'company'])]]);
        $id = DB::table('hr_holidays')->insertGetId([...$data, 'organization_id' => $org->id, 'created_at' => now(), 'updated_at' => now()]);
        AdminAudit::record('attendance.holiday.created', 'hr_holidays', $id, null, $data, $org->id);
        return back()->with('success', 'Holiday added to the organization calendar.');
    }

    public function timesheetStore(Request $request)
    {
        $this->enabled(); abort_unless($request->user()->can('attendance.self'), 403); $org = $this->organization($request); $employee = $this->selfEmployee($request, $org);
        $data = $request->validate(['work_date' => ['required', 'date', 'before_or_equal:today'], 'activity' => ['required', 'string', 'max:180'], 'description' => ['nullable', 'string', 'max:2000'], 'hours' => ['required', 'numeric', 'gt:0', 'lte:24']]);
        $id = DB::table('hr_timesheets')->insertGetId([...$data, 'organization_id' => $org->id, 'employee_id' => $employee->id, 'status' => 'submitted', 'created_at' => now(), 'updated_at' => now()]);
        AdminAudit::record('attendance.timesheet.submitted', 'hr_timesheets', $id, null, ['work_date' => $data['work_date'], 'hours' => $data['hours']], $org->id);
        return back()->with('success', 'Timesheet entry submitted.');
    }

    public function timesheetUpdate(Request $request, int $timesheet)
    {
        $this->enabled(); $org = $this->organization($request); $item = DB::table('hr_timesheets')->where('organization_id', $org->id)->find($timesheet); abort_unless($item, 404); $employee = HrEmployee::where('organization_id', $org->id)->findOrFail($item->employee_id);
        if (in_array($request->input('action'), ['approved', 'rejected'], true)) {
            abort_unless($request->user()->can('attendance.timesheets.manage') || $this->canReviewEmployee($request, $employee), 403); abort_unless($item->status === 'submitted', 422); abort_unless((int) $employee->user_id !== (int) $request->user()->id, 403);
            DB::table('hr_timesheets')->where('id', $item->id)->update(['status' => $request->input('action'), 'decided_by' => $request->user()->id, 'decided_at' => now(), 'updated_at' => now()]);
            AdminAudit::record('attendance.timesheet.decided', 'hr_timesheets', $item->id, ['status' => 'submitted'], ['status' => $request->input('action')], $org->id);
            return back()->with('success', 'Timesheet '.$request->input('action').'.');
        }
        abort_unless((int) $employee->user_id === (int) $request->user()->id && $request->user()->can('attendance.self'), 403); abort_unless($item->status === 'submitted', 422);
        $data = $request->validate(['action' => ['required', Rule::in(['cancel'])]]); DB::table('hr_timesheets')->where('id', $item->id)->update(['status' => 'cancelled', 'updated_at' => now()]);
        AdminAudit::record('attendance.timesheet.cancelled', 'hr_timesheets', $item->id, ['status' => 'submitted'], ['status' => 'cancelled'], $org->id);
        return back()->with('success', 'Timesheet entry cancelled.');
    }
}
