# Module 11 — Human Resources & Employee Records

**Status:** Installed locally. The local migration creates HR employee, contract, encrypted document, job-history, lifecycle-task, restricted-case, and training tables. The identity seeder grants the Human Resources role the Module 11 permissions. Active Administrator/superadmin accounts receive all permissions.

## Access and organization scope

Open Human Resources → **Employee directory**. Staff need `hr.view`; the Human Resources role receives Module 11 access. HR records are organization-specific. Non-administrators can access only their active organization memberships. Other roles should receive only the relevant permissions:

- `hr.manage` — employee profiles, assignments, lifecycle tasks.
- `hr.sensitive.view` and `hr.sensitive.manage` — personal/emergency details and restricted contract/case text.
- `hr.contracts.manage` — employment contract records and renewal dates.
- `hr.documents.view` and `hr.documents.manage` — encrypted HR document downloads and uploads.
- `hr.cases.view` and `hr.cases.manage` — restricted disciplinary case review and updates.
- `hr.training.manage` — course catalogue and employee training/certificate records.
- `hr.reports.view` — headcount, average tenure, turnover and separation summaries.

## Employee directory and assignment

Create a unique employee number within the organization. Enter the employee's work identity, employment type, lifecycle status, start/end dates, and organization assignment. Link an ERP account only when that account is active and already belongs to the organization. Personal and emergency contact fields appear only to staff with sensitive-data access.

The HR profile keeps department, team, branch, position and manager as HR-record assignment fields. For linked ERP users, also update Organization structure membership so its reporting hierarchy and data scope stay aligned; HR assignment fields alone do not alter application authorization.

Profile changes to assignment, employment type, and status are recorded as encrypted assignment history. Contract records can include dates, renewal date, status, and encrypted terms. Renewal dates within 90 days appear on the HR dashboard.

## Documents, cases and training

Upload PDF, Office documents, or common image files up to 10 MB. The application encrypts the bytes with the Laravel `APP_KEY` before storing them on the private local disk. Downloads require `hr.documents.view`; the UI never exposes filesystem paths. Back up both encrypted files and the matching production key. Losing the key prevents decryption.

Disciplinary case details and resolution are encrypted in the database and shown only to users with the restricted case permissions. Use training courses to record employee completion status, dates, expiries and an optional certificate document.

## Onboarding, offboarding and account access

Create onboarding and offboarding checklist items, assign them to active organization members, add due dates and notes, and mark them complete. Templates and automatic task creation are not implemented.

Marking an employee record **Terminated** does not automatically disable the linked application login. Go to Administration → **Users & access**, suspend or deactivate the account, and verify that its sessions are revoked. The identity workflow protects the last active Administrator account.

## Reports and boundaries

The page reports employee totals by status, average recorded tenure for profiles with hire dates, separations in the last 12 months, a simple turnover percentage, and contract renewals due within 90 days. These are operational summaries, not payroll or finance figures.

Module 11 does not include payroll, attendance clocks, leave balances, asset transactions, automated approval workflows, self-service employee access, or legal determination of retention requirements. Restrict HR permissions, establish retention rules, and review applicable employment and privacy requirements before production use.

## Deployment

Deploy the application code and run the Module 11 migration and identity seeder. There are no Module 11 `.env` values; the Laravel `APP_KEY` encrypts restricted database fields and document files. Do not rotate the key without a controlled re-encryption plan, and never replace the production `.env` with the local one.
