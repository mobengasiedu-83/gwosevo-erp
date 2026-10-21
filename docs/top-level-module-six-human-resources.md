# Top-level Module 6 — Human Resources

**Status:** Local implementation consolidated from the existing HR, recruitment, performance and attendance modules. No production deployment is implied.

## Menu

The Human Resources menu follows the approved top-level structure: **Employees, Recruitment, Assessments, Onboarding, Attendance, Leave, Appraisals, Training, Self-Service, HR Reports**. The HR **Overview** remains at the start of the menu and summarizes available HR work.

Menu entries appear only when the related module is enabled and the signed-in user has the relevant permission. The existing organization and employee scoping remains in effect.

## Workflows

- **Employees:** organization-scoped employee directory, profiles, reporting relationships, employment contracts, private employee documents, assignment history, lifecycle tasks and restricted case records.
- **Recruitment:** requisitions and approval, vacancies, applicant stages, interviews, offers and hiring. Public vacancy and candidate response pages remain available through their existing links.
- **Assessments:** assessment templates, candidate assignments, candidate submissions, scoring and review are managed from the Recruitment workflow.
- **Onboarding:** a new organization-scoped queue displays preboarding employees and onboarding checklist status. Add, assign and complete checklist tasks from each employee profile.
- **Attendance:** shift schedules, recorded attendance, holidays, exceptions, timesheets and attendance summaries.
- **Leave:** leave types and balances, employee requests, cancellation and approval actions use the existing attendance access and approval rules.
- **Appraisals:** review cycles, self-assessments, manager reviews, calibration, approval, feedback, goals and development plans.
- **Training:** organization course catalogue and employee training records are available in one view; edit or record a course against an employee from the employee profile.
- **Self-Service:** employees with the self-service permission can view their own records, clock in/out, request leave, and submit timesheets. Supervisors and HR retain only their existing permitted scope.
- **HR Reports:** organization headcount by status, recorded tenure, 12-month separations/turnover snapshot, and contract renewal dates in the next 90 days.

## Permissions and data boundaries

Employee profile access, sensitive personal data, employee documents, training management, HR reporting, recruitment actions, appraisal decisions, attendance administration and leave approvals use the existing `hr.*`, `recruitment.*`, `performance.*` and `attendance.*` permissions. Sensitive employee personal data and disciplinary cases remain separately permission-restricted. Organization ownership checks are retained for employee, recruitment, attendance and HR records.

## Existing policy boundaries

The implementation uses configured leave types, balances, approval settings, schedules and role permissions. It does not assume an unconfigured entitlement, approval threshold, training curriculum, assessment pass mark or retention period. The simple turnover percentage shown in HR Reports is terminated records with an end date in the last 12 months divided by current headcount; interpret it as a snapshot.

## Local verification

After pulling the local changes, use the normal application setup and open **Human Resources → Overview**. Review every submenu using the intended HR, manager, employee and recruitment roles. Existing migrations already provide the employee lifecycle and training data used here; no additional table migration is required. Seed role changes only if your local roles need the existing permission catalogue refreshed.
