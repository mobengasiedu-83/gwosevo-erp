# Module 14 — Attendance, Leave & Employee Self-Service

**Status:** Installed locally · **Navigation:** Human Resources → Attendance & leave · **Route:** `/attendance`

## Permissions and employee setup

Run `php artisan db:seed --class=IdentitySeeder` after deploying to register Module 14 permissions and update the built-in Human Resources role. Assign employees `attendance.view` and `attendance.self`; add `attendance.clock` for clock punches. Their HR employee record must link to an active ERP user in the organization. Managers also need an HR profile linked to their ERP user, and each report's manager relationship must be configured.

Human Resources is seeded with attendance management, leave balance, shift management, approval and report permissions. `attendance.approve` allows organization-wide approval; `attendance.manage` allows attendance administration. Direct managers can review their reports when they have `attendance.view`. Superadmin access bypasses individual permission grants.

## Shifts and attendance

Create a shift with a start/end time, weekly weekdays and grace period. You can assign the shift to one employee while creating it. An active linked employee can clock in and out once per calendar day. Clock-in status is late when it falls after shift start plus grace; clocking in on a weekday not in the assigned shift schedule is marked as an exception. Without an assigned shift, clock-in is recorded as present.

Users with `attendance.manage` can add or correct attendance records, including status, timestamp and exception reason. Absence records must be entered manually; the module does not run an automatic attendance scan or payroll export.

## Leave policy and requests

1. Create leave types and set whether requests require approval, whether the leave is paid, and the default entitlement.
2. Use **Set employee leave balance** to assign each employee's entitlement, carryover and adjustments for the calendar year. Approved leave consumes the available balance.
3. Enter company and Ghana public holidays in the organization calendar. Dates are not imported from an external feed.
4. Employees submit date ranges and an optional reason. The system counts weekdays excluding organization holidays, rejects overlapping pending/approved requests, rejects requests spanning calendar years, and checks available balance.
5. Approval-required requests remain pending until an authorized manager or HR user approves/rejects them. Non-approval leave types are immediately approved by policy. Employees may cancel a pending request.

Leave request reasons are encrypted with `APP_KEY`. The HR employee status is not automatically changed to `on_leave` or restored after the leave period; manage employment status through the HR record when needed.

## Timesheets and reporting

Employees can submit timesheet entries with work date, activity, description and hours. Their manager or an HR approver may approve or reject submitted entries. Employees can cancel submissions while pending. Entries are not connected to projects or billing until Module 15 is implemented.

Users with `attendance.reports.view` can see current-month counts for present, late, absent and approved leave days. Shift schedules, holiday entries, manual corrections, leave decisions and timesheet decisions are audit logged.

Set local HR policies for entitlements, attendance corrections, leave approvals, holidays, shift changes, overtime, retention and payroll before production use. This module does not accrue leave automatically, generate absences, send reminders, manage rotating shift patterns or enforce overtime/pay rules.
