# GWO SEVO ERP — User Guide

**Edition:** 20 October 2026 · **Audience:** staff, supervisors, reviewers and administrators
**Application:** <https://localhost/gwosevo-erp/public/>

This guide describes the application currently installed in this repository. Modules 01–08 are installed within the commissioning boundaries described here. Modules 09–22 remain planned and do not have operational user screens yet. Their feature catalogue is included in the [module roadmap](module-roadmap.md).

The names “superadmin” and “Administrator” refer here to the existing Administrator account/role. This guide contains no account passwords, invitation tokens or integration secrets.

## Contents

1. [Getting started and navigation](#getting-started-and-navigation)
2. [Roles and access](#roles-and-access)
3. [Module 01 — System administration and configuration](#module-01--system-administration-and-configuration)
4. [Module 02 — Identity, users, roles and permissions](#module-02--identity-users-roles-and-permissions)
5. [Module 03 — Organization, departments and branches](#module-03--organization-departments-and-branches)
6. [Module 04 — Current CRM lead workflows](#module-04--crm-and-lead-management)
7. [Module 05 — Sales pipeline and opportunities](#module-05--sales-pipeline-and-opportunities)
8. [Module 06 — Quotations, contracts and client onboarding](#module-06--quotations-contracts-and-client-onboarding)
9. [Module 07 — Contact centre and VitalPBX](#module-07--contact-centre-and-vitalpbx)
10. [Module 08 — Customer support and ticketing](#module-08--customer-support-and-ticketing)
11. [Modules 09–22 — Planned capabilities](#modules-0922--planned-capabilities)
12. [Daily and periodic routines](#daily-and-periodic-routines)
13. [Troubleshooting](#troubleshooting)
14. [Administrator setup checklist](#administrator-setup-checklist)
15. [Glossary](#glossary)
16. [Technical and commissioning notes](#technical-and-commissioning-notes)

## Getting started and navigation

### Sign in

1. Open the application URL above. The application redirects to its dashboard or sign-in screen.
2. Enter your existing email address and password and choose **Sign in**. The password visibility button lets you check your entry.
3. If your account already has an authenticator, complete the challenge with a current authenticator code or an unused recovery code.
4. If enrollment is required, follow the setup in [My security](#my-security-authenticator-password-and-sessions).

Mandatory authenticator enrollment and Microsoft sign-in are currently disabled by the operator's development settings. Disabling mandatory enrollment does not remove an existing authenticator. Existing passwords are unchanged.

New staff normally receive an email invitation. Open that link, enter and confirm a new password, and submit the form. Invitations expire after 48 hours and can be used once. Ask an administrator to resend an expired invitation. Use **Forgot password** on the login screen to request a password-reset link; the response intentionally does not reveal whether an email address exists.

### Use the workspace

- **Overview** is the dashboard. Lead totals, pipeline, follow-ups, activity and shortcuts depend on your access and enabled modules. They are not organization-wide figures unless your scope permits it.
- The dark sidebar groups pages under Workspace, Commercial, Governance, Administration and Account. Only authorized links appear.
- Click a group heading to expand it. Other groups collapse automatically; the current section opens on page load.
- On desktop, use the top-left menu button to switch between the full sidebar and the icon rail. The browser remembers that preference. Hover over a compact icon to see its label.
- On mobile, use the menu button to open navigation. Close it with the close button, the overlay or Escape.
- Press **Ctrl K** or **Cmd K**, or choose **Quick navigation**, then type a page name. Use arrow keys and Enter to choose a result; Escape closes the dialog.
- Colored tabs open related sections. The selected tab is highlighted. Animations follow your operating system's reduced-motion preference.
- When an active-organization selector appears, choose the organization and click **Switch** before entering records for it. Organization membership is required; switching does not grant additional access.
- Use the top-right account menu for **My security** and **Sign out**. Sign out when leaving a shared workstation.

### Save, search and confirm

Forms show validation messages when a required field or relationship is invalid. Correct the indicated fields and submit again. A success notice confirms a completed operation. Do not assume a change was saved if an error appears.

List pages offer search and pagination where supported. Use the form's search button to apply a search. A blank list can mean no records, a search with no matches, or restricted access.

Actions such as session revocation and review completion show a confirmation dialog. Read the consequence before choosing **Confirm change**. **Cancel** leaves the action unapplied.

## Roles and access

| Person | Typical work | Access boundary |
| --- | --- | --- |
| Administrator | Configure settings, maintain users/roles and organization structure, inspect audits | Active Administrator role required for management screens |
| Staff member | Use permitted workspace and CRM actions; manage personal security | Role/direct permissions plus organization and record scope |
| Supervisor | Review permitted team/department records and organizational relationships | A job title or supervisor assignment alone does not grant permissions |
| Assigned reviewer | Inspect assigned access reviews and record decisions | Review assignment or Administrator role required |
| Delegate | Exercise valid delegated approval authority in a consuming workflow | Delegation does not grant wider record access or general role permissions |

Permissions answer **what you may do**. Membership scope answers **which records you may use**:

| Scope | Current CRM meaning |
| --- | --- |
| Own | Leads owned by you; unassigned leads use their creator |
| Team | Leads accessible through the selected team membership |
| Department | Leads accessible through the selected department membership |
| Organization | Leads throughout the permitted organization |

A role name, permission definition, position or future-module entry does not mean that an unimplemented business workflow exists. Contact an administrator when an action or record you need is unavailable.

## Module 01 — System administration and configuration

**Who:** Administrator. **Open:** Administration → **System configuration**.

### General settings

1. Open **General**.
2. Maintain company name, contact email, phone, address, time zone and date format.
3. Save and check the success notice.

These settings control presentation and company details. They do not rename legal-entity records or change database login credentials. Dates are stored in UTC and displayed using the configured presentation time zone.

### Branding

1. Open **Branding**.
2. Upload an approved PNG, JPEG or WebP logo if replacing the supplied GWO SEVO identity.
3. Set the accent color and login message; maintain document header/footer settings where shown.
4. Save, then inspect the login screen and a reference-data printout.
5. Use the logo-removal option if returning to the default identity.

The shared interface uses GWO SEVO imagery, fonts and green/red/yellow accents. Branded PDF output currently uses the browser's Print / Save as PDF function.

### Preferences

Open **Preferences**, select a three-letter currency code, financial-year starting month and page size, then save. Pagination is applied by supported lists. Currency/financial-year preferences do not create a finance ledger or convert existing amounts automatically.

### Module availability

Open **Modules** to see availability and configuration. CRM can be enabled or disabled; disabling it also blocks direct access to its lead pages. Administration, identity and organization are protected core modules. Planned modules cannot be enabled merely by toggling an entry.

### Custom lead fields

1. Open **Custom fields**.
2. Add a lead field with a permanent key, label and supported type: text, number, date or select.
3. For a select field, provide its allowed options. Mark it required only when users can supply the information.
4. Activate and save the field.
5. Open a lead form and check the new field.

Keys and types are immutable. Edit labels/options and availability through the existing record. Required fields are validated on lead saves. Deactivation retains previously stored values. Custom fields for other business record types await those modules.

### Reference data

Open **Reference data** to maintain countries, regions, industries and customer categories. Use uppercase codes with letters, numbers, underscores or hyphens. Add a country before a region that references it. Edit labels or deactivate obsolete entries instead of inventing replacement codes for the same meaning.

Example: create country code `GH`, label `Ghana`; then create a region linked to country code `GH`.

### Number sequences

1. Open **Numbering**.
2. Select the record sequence and review its current prefix, padding and next value.
3. Make an approved change and save.
4. Check the reference generated for the next new record.

Lead/customer allocation is transactional. Existing references stay unchanged, and next numbers cannot be moved backward into used values. Ticket, quotation and invoice sequence definitions are reserved for their future modules; they do not provide those record-creation screens.

### Import reference data

1. Open **Import / export** and prepare a CSV or XLSX file with a header row.
2. Use category, code and label columns; add parent_code and is_active when needed.
3. Upload the file and map your columns to the target fields.
4. Run the preview and resolve every validation error.
5. Review proposed updates as well as additions: category/code identifies an existing record.
6. Commit the approved preview once, then verify the reference list and audit entry.

Accepted categories are `country`, `region`, `industry`, and `customer_category`. Region parent codes must identify an existing country or one in the same import. Active values are `1` or `0`, defaulting to `1`.

Limits: UTF-8 comma-delimited CSV or `.xlsx`; maximum 2 MB uploaded, 1,000 data rows and 50 nonempty distinct headings. XLSX uses its first worksheet and supports values, not formulas/macros or legacy `.xls`. Expanded workbook limit is 20 MB. Invalid rows block the entire commit. Preview expires after 30 minutes and belongs to the signed-in user. Remapping requires a new preview. This tool does not import users or arbitrary ERP tables.

### Export and print

Open **Import / export**, choose the supported reference category/filter and export CSV or XLSX. Treat exported information according to company policy. Use the branded print option and your browser's **Save as PDF** to produce a PDF. Check the destination and page preview before saving or printing.

### Health and maintenance

**System health** checks database, storage, cache, queue connection and scheduler/worker heartbeats. Refresh the panel or use its queue probe. A healthy connection does not prove a background worker is continuously running. Missing/stale heartbeats should be referred to the operator.

**Maintenance** lets an administrator set an operational notice or enable maintenance mode. Staff/guests receive the maintenance message while administrators retain recovery access. After maintenance, disable it and confirm normal staff access. Operational notices are informational and do not themselves stop the application.

### Audit history

Open **Audit history** in System configuration to inspect configuration and structure changes, imports/exports and related activity. Expand before/after values where available. Audit entries cannot be edited through this interface. Identity-specific history also appears under **Audit activity**.

## Module 02 — Identity, users, roles and permissions

### Invite a user

**Who:** Administrator. **Open:** Administration → **Users & access**.

1. Choose the user-creation/invitation action.
2. Enter the person's name and email carefully.
3. Select at least one role and any approved direct permissions.
4. Add organization memberships. Choose each membership's scope and valid department/team where required.
5. Select an active organization that is included in those memberships.
6. Set the account's MFA requirement as appropriate and submit the invitation.
7. Confirm that the account is listed as invited. The recipient activates it using the email link.

Use **Organization structure** for branches, positions, managers and cost allocations after membership exists. Team/department assignments must belong to the selected organization. A team-scope membership requires a team; department scope requires a department.

Delivery failure leaves an invited account available for retry. Open that account and resend; do not create a duplicate. Resending invalidates previous invitations. Do not send or store an administrator's password in a user's invitation.

### Change access or account status

Open the user and edit roles, direct permissions, memberships, active organization, name or status as permitted. Save and confirm the success message. Access changes revoke existing sessions and API tokens, so the user must sign in again.

| Status | Meaning |
| --- | --- |
| Invited | Awaiting one-time invitation acceptance |
| Active | May sign in subject to permissions and MFA |
| Suspended | Access stopped; sessions revoked |
| Inactive | Account deactivated; access stopped |

An invited account becomes active by accepting its invitation. Existing accounts cannot be returned to invitation status. Existing email/password are not editable on this management screen. Use password recovery or personal password change instead.

The last active Administrator is protected. Administrators cannot demote/deactivate themselves through this screen. Reassign management/reporting responsibilities and remove allocations before removing a membership that other records depend on. If a position conflicts with a new department, update its assignment first.

### Roles and permissions

1. Open **Roles & permissions**.
2. Create a business role or edit an eligible existing role.
3. Select only the permissions needed for the work and configure its MFA requirement.
4. Save, then assign the role through **Users & access**.

Built-in roles include Administrator, Sales, Operations, Human Resources, Finance, Support and Read Only. Built-ins are protected; unused custom roles can be deleted where allowed. Role changes invalidate affected sessions. Direct permissions supplement role permissions; review both when investigating access.

View/create/edit/archive/export/approve/assign permissions are defined for appropriate consumers. Future-module permissions are definitions, not available screens or features.

### My security: authenticator, password and sessions

**Open:** Account → **My security**, or the account menu.

**Enroll an authenticator**

1. Enter your current password and choose **Start authenticator setup**.
2. Within ten minutes, add a time-based/TOTP account in your authenticator app using your email and the displayed setup key.
3. Enter its six-digit code and choose **Confirm and enable MFA**.
4. Store the displayed recovery codes privately. They are shown once and each works once.
5. On later sign-ins, enter a current authenticator code or unused recovery code.

Never share the setup key or recovery codes. Replacing recovery codes requires current authentication and invalidates the old set. Optional MFA can be disabled only when the account's current requirements permit it; the action ends sessions. If all recovery methods are lost, contact another Administrator. Their administrative reset requires their own password and authenticator/recovery proof plus a recorded verification reason; your password is not changed.

**Change password**

Enter your current password, authenticator/recovery proof if enrolled, and the new password twice. New passwords require at least 12 characters with uppercase, lowercase, a number and a symbol. Choose **Change password and sign out**, then sign in again. Password reset/change ends existing sessions and does not remove MFA.

**Review sessions**

Inspect device, IP, sign-in method and last-seen information under **Active sessions**. Use **Revoke** for an unrecognized or obsolete session. Revoking the current session signs you out. Administrators can revoke all sessions/tokens from the user-management screen.

### Microsoft sign-in and email

Open **SSO & email** to inspect configuration status. Microsoft sign-in is intentionally disabled until the operator configures its secret, exact registered callback and user identity links. When enabled, users must have explicit Entra tenant/object-ID links; matching email addresses alone do not link accounts or create users. Local MFA still applies to enrolled accounts.

SMTP is configured, but real invitation/reset delivery must be accepted with the operator. A configured status is not proof of delivery. Connection settings belong in `.env`; do not paste secrets into this guide or audit notes.

### Approval delegation

1. Open **Approval delegation**.
2. Select the delegator and delegate. A normal user delegates their own eligible authority; administrators can manage eligible principals.
3. Choose the supported approval permission, start/end time and a clear reason.
4. Save and inspect its scheduled/active/expired/revoked state.
5. Revoke the delegation when it is no longer needed.

The maximum duration is 90 days. Both parties need eligible organization membership and the principal must hold the relevant authority. Delegation never expands record visibility or general role permissions. The current screen defines CRM approval authority; it does not add a new lead-approval business step. Future workflows consume the authority service.

### Access reviews

**Administrator**

1. Open **Access reviews**, create a named review, assign an active reviewer and set a future due date.
2. Ensure the reviewer records a decision and evidence for every included user.
3. Inspect all decisions before choosing **Apply decisions and lock review**.
4. Confirm only when revocations are intended. Completion applies them and locks the review.

**Assigned reviewer**

1. Open an assigned review and inspect account status, roles, permissions and membership snapshot.
2. Choose **Retain access** or **Revoke access**, enter a reason/evidence, then **Record decision**.
3. If access has changed since the snapshot, use **Refresh access snapshot and clear decision**, inspect the new state and record a new decision.
4. Notify the administrator that all decisions are ready for completion.

A recorded decision is not a completed revocation. Completion requires all decisions, current snapshots and Administrator authority; last-administrator safeguards still apply. Completed reviews are locked. Scheduled creation of periodic reviews depends on the host scheduler.

### Identity audit

Administrators open **Audit activity** for login attempts and identity changes. Inspect timestamps, actor/user information and available change details. Passwords, MFA secrets, raw tokens and raw session identifiers are excluded from audit metadata.

## Module 03 — Organization, departments and branches

**Open:** Workspace → **Organization structure**. Administrators maintain records. Staff can view organizations in which they have active membership.

### Recommended setup order

1. Create legal entities.
2. Create/link operating organizations and any parent organizations.
3. Grant user memberships in **Users & access**.
4. Create departments, branches, teams, positions and cost centres.
5. Assign people and managers, then verify the reporting/approval charts.

Existing operating organizations can remain usable until their actual legal details are entered. Do not invent registration numbers or recreate an existing organization merely to add those details.

### Legal entities and operating organizations

On **Organization structure**, open **Legal entities → Add legal entity**. Supply a permanent code/name, registration and tax details when known, contact/address, currency, time zone and availability. Save.

Open **Manage organizations → Add organization** to create an operating organization. Enter code/name, legal entity, optional parent, contact/address, time zone and availability. Parent and child organizations must share a legal entity. Parent relationships cannot form cycles.

Codes are permanent. Change a name by editing the existing record. Reassign dependent children before changing a parent's legal-entity relationship.

### Create a department

1. Select the organization card, such as **GWO SEVO Solutions**.
2. Open **Departments → Add department**.
3. Enter code/name, optional parent department, manager and purpose/responsibilities.
4. Leave it active and save.

The manager must be an active member of this organization. A parent must be an active department in the same organization. The application rejects circular parent relationships.

### Create a branch

Open the selected organization's **Branches → Add branch**. Enter code/name, manager, contact email/phone, office address, city and two-letter country code, such as `GH`. Save. Branches describe office or operational locations and can be assigned to people.

### Create a team and add members

1. Open **Teams → Add team** inside the organization.
2. Enter code/name, department, supervisor and responsibilities; save.
3. Open **People & chart** and choose **Edit assignment** beside a person.
4. Select the matching department and team, then save.
5. Repeat for other members and inspect the roster.

This implementation uses one primary team per person's organization membership. The supervisor must be an active organization member. A team with a department requires compatible member assignments. Team membership does not automatically change the user's role or scope.

### Create positions

Open **Positions → Add position**. Enter code/title, department, reports-to position, grade/level and job description. Save. Position-to-position reporting cannot contain a cycle. Assign a position to a person through **Edit assignment**; its department must match when one is specified.

A position relationship describes the structure. Set the person's reporting manager separately; choosing a position does not automatically appoint a person as manager.

### Create cost centres

Open **Cost centres → Add cost centre**. Enter code/name, department if applicable, parent centre, budget owner, cost/revenue/both classification, three-letter currency and optional nonnegative budget. Save.

Cost centres organize allocation metadata. Budgets do not post accounting entries, implement financial approvals or enforce finance spending controls in this module.

### Assign a person and cost split

1. Open **People & chart → Edit assignment** beside a member.
2. Select department, team, branch and position from this organization's active records.
3. Select a reporting manager and, if needed, a separate approval manager.
4. Leave allocation rows empty for no cost allocation; otherwise choose distinct active centres and percentages totaling exactly 100%.
5. Save and review the roster/chart. Saving reveals an additional blank allocation row when needed.

Example: 60% to `DELIVERY` and 40% to `HQ`, or 33.33% and 66.67%. The application rejects duplicates, foreign/inactive centres and totals such as 99.99%.

A membership with team/department access scope must keep the corresponding assignment. Change the scope in **Users & access** first if removing it. Department/team assignment changes end sessions so the record-scope change takes effect immediately; passwords remain unchanged.

### Reporting and approval lines

A reporting manager and approval manager must be active members of the same organization. A person cannot report to themselves or participate in a circular chain. An omitted approval manager uses the reporting manager. Both the reporting graph and this effective approval graph are checked for cycles.

On **People & chart**, choose **Reporting lines** or **Approval lines** and click **View**. Expand/collapse a person's node to explore their team. Nodes show active people, positions, departments and teams. The roster includes assignments and cost splits. Manager/approval relationships are organizational lines; business approval steps are delivered by their own modules.

### Edit or deactivate structure records

Open the relevant catalogue, search for a code/name and choose **Edit**. Names/details can change; codes cannot. Set availability inactive only after reassigning active members and dependent records. Records are retained rather than deleted.

If deactivation is blocked, inspect child organizations/departments/positions/centres, active teams, person assignments and allocations. Reassign or deactivate dependencies in the required order, then retry. Never bypass the screen by deleting database rows.

Structure changes are audited under **System configuration → Audit history**.

## Module 04 — CRM and lead management

**Status:** Installed. Open Commercial → **CRM leads**. All lead lists, reports, imports, duplicate candidates and actions use your active organization and record-access scope.

### Capture a lead

1. Choose **Capture lead**.
2. Enter the contact and company details, source, and optional source detail. Add custom fields when configured.
3. Save. The system creates a reference, calculates fit and engagement scores, detects possible duplicate contacts, and assigns the lead by matching rule or round-robin when an eligible owner is available.

Available sources are Manual, Website, API, Approved import, Referral, Inbound call, Event and Campaign. Source detail can identify the event, referring person or campaign. Website and API captures are provided by signed/authorized server integrations; a source label alone does not create an integration.

### Search and filter

Use the CRM leads list to search names, company, email and phone. Filter by status or source. Users with archive permission can include archived leads and restore them. Opening a lead shows its profile, score components, activities, status history, conversion links and any duplicate review flags.

### Assign and qualify

Owners can be assigned manually from the lead page. Administrators or users with **Lead assignment rules** access can create source/industry rules; lower priority numbers run first. Leads without a matching rule rotate among active CRM members in the organization.

Enter budget, decision authority, business need and timeline in **Qualification**. Marking the lead qualified records the status change. Fit score reflects profile and qualification completeness. Engagement score reflects recorded activities and completed tasks. The total uses the configured fit/engagement weights (defaults: 60/40).

### Review possible duplicates

The system flags same-organization matches on normalized phone number, case-insensitive email or exact company name. It does not merge or delete records. A user with duplicate-review permission can link a lead to an accessible canonical record, or mark it unique. Resolve duplicate flags before conversion when appropriate.

### Record activities and follow-ups

Add calls, meetings, notes and tasks to the activity timeline. Give tasks a due date/time, then mark them complete from the lead page. Set **Next follow-up** on the lead profile for a separate lead-level reminder. The scheduler sends in-app reminders every 15 minutes and escalates overdue follow-ups after the configured number of hours (default 24). Escalation goes to an authorized reporting manager, or an Administrator if no suitable manager can access the lead. The scheduler must be running for reminders and escalations to be delivered.

### Import leads

Users with import permission can upload a UTF-8 CSV (up to 4 MB and 1,000 data rows) with `first_name` and `last_name`. Optional columns are `email`, `phone`, `company_name`, `industry` and `interests`. Review the valid-row preview and any row errors before committing. Imported leads are tagged **Approved import**, automatically assigned, scored and checked for possible duplicates. A preview belongs to your session and active organization; if it expires or you switch organizations, upload it again.

### Website and API integrations

- **Website capture:** `POST /api/v1/leads/capture/{organization-code}` accepts validated JSON, signed with HMAC-SHA256 in `X-CRM-Signature`. The shared secret is `CRM_WEBSITE_CAPTURE_SECRET`. Keep the secret on a trusted server and proxy browser form submissions through that server. Send a unique `Idempotency-Key` with each submission.
- **Authenticated API capture:** `POST /api/v1/leads` uses a Sanctum token whose user has `leads.create` and an active organization. Send a unique `Idempotency-Key`; repeat submissions return the existing lead when the payload is unchanged and return conflict when the same key is reused with different data.

Website capture is disabled until the operator sets the secret in `.env` and clears the configuration cache. Both endpoints are rate limited. Keep credentials out of browser code and logs.

### Convert, disqualify and archive

Only qualified leads can be converted. Conversion creates or reuses an account, creates a contact and opportunity, links the records, and audits the result. Disqualify a lead with a reason when it should not proceed. Archive records to remove them from normal lists without deleting their history; authorized users can restore them.

### Reports

Open **Reports** for lead totals by source and status, conversion rate, ageing bands and owner productivity. Metrics and CSV export respect your record scope. CSV export requires the separate export permission.

## Module 05 — Sales pipeline and opportunities

**Status:** Installed. Open Commercial → **Sales pipeline**. Records, forecasts and reports are restricted to your active organization and opportunity access scope.

### Configure the sales catalog

An Administrator opens **Pipeline setup** and configures the four approved strategic service lines and seven approved integrated service packages. The supplied specifications do not include their names, so they are not fabricated or preloaded. Codes must be unique within the organization. The setup page also lets Administrators adjust stage labels, order and default probability. Move opportunities out of a stage before deactivating it.

Set up quotas under **Sales targets**. Select exactly one person or team and define the target period and revenue/pipeline amounts. Reports compare authorized won revenue and open pipeline for the target period.

### Create and manage opportunities

1. Choose **New opportunity** or open a qualified lead and use its conversion action. Lead conversion creates an opportunity in the **New** stage.
2. Enter the deal name, account/contact, owner, amount, expected close date and configured service line/package.
3. Move the opportunity through New, Contacted, Qualified, Needs Assessment, Proposal/Quotation Sent, Negotiation, Awaiting Customer Decision, Won, Lost or On Hold. Probability follows the configured stage value.
4. Add calls, meetings, notes and tasks on the opportunity page. Due tasks appear on the workspace dashboard; scheduled reminders require the Laravel scheduler.
5. Record competitor names and notes. A loss requires a reason. Won/Lost opportunities are closed and cannot be reopened through the pipeline.

Use the Kanban board and table to review the pipeline. The weighted forecast is amount multiplied by stage probability. Stage history and commercial changes are audited.

### Request commercial approval

On an opportunity, submit the discount percentage or nonstandard terms with a justification. An authorized approver reviews and records approval or rejection; requesters cannot decide their own request. A pending request blocks closing Won. Nonstandard terms or a declined discount require an approved request before Won. Each discount or special-terms request follows this approval step.

### Forecasts and reports

Open **Forecast & reports** for stage totals, weighted open value, win rate, average won-deal sales cycle and person/team target attainment. CSV export respects the same access scope. A weekly scheduler captures dated opportunity forecasts. Forecast accuracy compares the last available weighted forecast captured before a month begins with Won value recorded for that month; it will show **Collecting** until a completed month has a preceding snapshot.

### Setup and access boundaries

The Module 05 migration enables the module. The permission seeder grants opportunity view/create/update/assign/report permissions to Sales, approval/report permissions to Finance, view to Read Only, and all Module 05 permissions to Administrator. It adds these grants without removing other role permissions. Administrators manage the service catalog, stages and quotas. Review direct permissions and organization scope when access is insufficient.

The host must run Laravel's scheduler for weekly forecast snapshots and 15-minute due-action reminders. Existing lead-conversion opportunities receive references and initial stage history during migration. Quotations, contracts and client onboarding are implemented in Module 06.

## Module 06 — Quotations, contracts and client onboarding

**Status:** Installed. Open Commercial → **Quotes & contracts**. Access follows your active organization and opportunity data scope. See the [Module 06 operations guide](../module-six.md).

Create a quotation from an opportunity. Add fixed, recurring, usage-based or negotiated pricing lines, then submit the draft with a justification for independent approval. Approved and rejected quotes are preserved; make changes by creating a new version. Use **Print / Save PDF** for a customer-ready quote.

Create a contract draft from an approved quote. Add its term, renewal date, SLA, deliverables and approved template, then upload the signed agreement before activating it. Maintain onboarding checklist items and private documents; explicitly mark material client-visible before generating an expiring client link. Handover requires all checklist items complete or approved, and another user must accept it.

The system sends renewal reminders for active contracts within 30 days and lets authorized users create a linked renewal opportunity. Run the host Laravel scheduler daily. The module does not calculate tax, execute electronic signatures, or supply legal contract language or pricing thresholds. Configure company-approved templates, pricing rules and role permissions before live use.

## Module 07 — Contact centre and VitalPBX

**Status:** Installed. Open Operations → **Contact centre**. Records and matching are scoped to the active organization.

The screen provides call and queue summaries, inbound/outbound/missed/abandoned call logging, customer matching by phone, disposition notes and follow-up time, agent extension mappings, agent availability, QA forms and 0–5 evaluation scoring. `calls.view` grants view access; `calls.disposition` allows call logging and disposition; `calls.configure` manages PBX profiles and mappings; `calls.qa.manage` manages QA; and `calls.recordings.view` reveals recording references. Administrator receives all permissions; Support gets view/disposition; Operations gets view/disposition/supervise. Review role assignments before use.

To commission a provider, an authorized user creates a PBX profile. The webhook signing secret is shown once in the success message; store it in the approved PBX configuration. Set the approved HTTPS API base URL, then activate the profile to accept signed inbound events at `/api/v1/pbx/{connection}/events`. Requests use `X-PBX-Timestamp` and `X-PBX-Signature`, where the signature is HMAC-SHA256 over `<timestamp>.<raw request body>` using the profile secret; timestamps must be within five minutes. Events are rate limited. The supported payloads are `call.completed` and `agent.activity`; consult the integration owner before mapping VitalPBX/Asterisk events to them.

**Provider commissioning remains required:** configure the VitalPBX HTTPS API key and tenant in `.env`, map each ERP user to their own extension, and grant `calls.dial` to users allowed to place calls. The Administrator role includes this permission; restrict it through Roles & Permissions if some administrators should not dial. **Click to dial** rings the selected user's mapped extension first; after they answer, VitalPBX calls the entered destination. The API key stays on the server. Recording links are stored as references only; downloads are not provided. The known CDR `getRecordingAuthParam()` exception is raised by VitalPBX and must be corrected by the PBX vendor/update path.

For manual calls, enter the event and number details; the ERP attempts an exact phone-digit match against the active organization. Verify caller identity before linking the call to a customer. Add a disposition and follow-up after the call. Supervisors create a quality form using one criterion per line, then score each criterion from 0 to 5.

## Module 08 — Customer support and ticketing

**Status:** Installed. Open Operations → **Support tickets**. Access is scoped to the active organization and controlled by `tickets.*` permissions.

Create a ticket with requester details, category, priority, related CRM contact/account, or a call record. Assign a team and agent, update the lifecycle (open, assigned, in progress, waiting, resolved, closed), and add customer-visible replies or internal notes. Attachments are stored on the private local disk and downloaded only by authorized organization members or through the ticket's customer portal link.

Administrators or users with `tickets.manage` add categories and set response/resolution targets by priority. Targets are elapsed minutes, begin at ticket creation, and apply to new tickets. The scheduled `tickets:check-sla` command records each breach once and routes the ticket to its escalation owner. Keep the Laravel scheduler running (`schedule:run` once per minute) for breach detection. The ticket list summarizes open, unassigned, breached, reopened and average resolution hours.

Use the **customer portal submission link** `/support/submit/{organization-code}` for unauthenticated requests. The requester receives a private bearer link after submission; anyone holding that link can read public ticket messages, reply, download public attachments and submit satisfaction feedback after resolution. Treat the link like a password. Internal notes remain hidden.

Authenticated integrations can create tickets at `POST /api/v1/tickets` using a Sanctum token for an active user with `tickets.create` in the active organization. An email-to-ticket service can forward parsed mail to this endpoint with `channel=email`; this ERP provides the secure intake API, while mailbox polling/forwarding remains part of the operator's mail setup. Call-centre agents can link a ticket to an existing call record on creation.

Attachments are limited to five per message, 10 MB each, with PDF, common image, text/CSV, Word and Excel formats. The standard local disk is private; configure encrypted private storage and backups for production. SLA calculations use elapsed time rather than business calendars. For staff/API tickets, requester emails receive public messages and the private portal link when the requester's address is supplied. Internal notes and attachments are excluded. Portal links are bearer credentials.

## Email activity log

Laravel email attempts and successful sends are recorded in `storage/logs/email.log`; failed mail notifications are marked as failures. Entries include recipient addresses, subject and message ID, but never message bodies or attachments. Follow the file with `tail -f storage/logs/email.log`. Ensure the production PHP user can write to `storage/logs`.

## Modules 09–22 — Planned capabilities

These modules do not yet have operational screens in this installation. The [complete module roadmap](module-roadmap.md) lists every specified module and feature, including the distinction between current foundations and future work.

| Module | Planned area |
| 09 | SMS and communication management |
| 10 | Marketing, campaigns and lead generation |
| 11 | Human resources and employee records |
| 12 | Recruitment and assessments |
| 13 | Performance management and appraisals |
| 14 | Attendance, leave and employee self-service |
| 15 | Projects, tasks and team productivity |
| 16 | Finance, invoicing and revenue management |
| 17 | Procurement, assets and inventory |
| 18 | Knowledge bank and AI search |
| 19 | Reports, dashboards and business intelligence |
| 20 | Notifications, approvals and automation |
| 21 | Integration and API management |
| 22 | Security, audit, backup and monitoring |

Detailed operational procedures for these modules must be written against their implemented screens and verified behavior when installed. The roadmap is not an instruction to activate or implement them now.

## Daily and periodic routines

| Frequency / person | Routine |
| --- | --- |
| Daily / staff | Confirm active organization; review permitted follow-ups; update lead details/activities; sign out on shared devices |
| When staff join / administrator | Confirm organization/team structure; invite user; assign least-needed roles/scope; verify activation and assignments |
| When staff move / administrator | Reassign department/team/position/manager/cost split; review scopes and direct permissions; expect session revocation |
| When staff leave / administrator | Suspend/deactivate the account; reassign responsibilities and record ownership as appropriate; review delegations and sessions |
| Periodically / reviewer | Inspect assigned access reviews, record evidence and notify administrator for completion |
| Periodically / administrator | Review privileged roles, direct permissions, session/login activity, unused reference/structure records and health indicators |
| Before organizational changes / administrator | Identify child records and members; plan reassignment before deactivation |
| Before import / administrator | Review source file, mapping, preview and existing category/code updates |

## Troubleshooting

| Symptom | Action |
| --- | --- |
| Cannot sign in | Check email/password; complete enrolled MFA; ask administrator about invited/suspended/inactive status; wait before retrying if rate-limited |
| Password-reset email does not arrive | Check spam and address; ask operator to verify SMTP delivery; the reset page deliberately does not disclose account existence |
| Invitation expired or delivery failed | Administrator opens the existing invited account and resends; use the newest link |
| Authenticator codes rejected | Check device time and current code; use an unused recovery code; request an administrator reset if recovery is exhausted |
| Session ended after an edit | Sign in again; access/password/role/team/department changes can deliberately revoke sessions |
| Menu/action missing | Check permissions, active organization and enabled module; planned modules have no screens |
| Department/team manager absent | Ensure the intended manager is an active member of that organization |
| Team or position rejected | Check its organization/department and availability against the person's assignment |
| Circular relationship rejected | Remove the loop; a manager/parent cannot ultimately report back to their own descendant |
| Cannot deactivate a record | Reassign active people and dependent child records/allocations first |
| Allocation rejected | Use distinct active centres in the same organization; enter up to two decimal places; total exactly 100% |
| Import rejected | Check file format/limits, headers, mapping, category/code/label, country parents and active values; generate a fresh preview |
| Review cannot complete | Record every decision; refresh stale snapshots and decide again; confirm an active Administrator will remain |
| Microsoft sign-in unavailable | It is currently disabled; operator must configure/accept the external integration before enabling it |
| Maintenance message | Wait for the announced window; an administrator can access recovery configuration |
| Expired session / 419 | Reload the page, sign in if needed, then retry from a fresh form; do not blindly resubmit irreversible actions |
| Forbidden / 403 | Ask an administrator to review authorization; changing the URL does not grant access |
| Missing page / 404 | Check the application's base URL and whether the module/record exists and is enabled |
| Worker/scheduler unhealthy | Refer to the operator; refreshing the screen does not start host services |

When escalating, provide the page name, time, active organization, record reference and displayed error. Do not send passwords, setup keys, recovery codes, invitation/reset links or client secrets.

## Administrator setup checklist

- Confirm company profile, branding, presentation time zone, currency and date preferences.
- Maintain legal entities and operating organizations using real company information.
- Grant initial manager memberships before selecting management owners.
- Create departments, branches, teams, positions and cost centres.
- Assign staff, reporting lines, approval overrides and approved cost splits.
- Verify role permissions and record scopes with representative staff accounts.
- Add required reference data and lead custom fields; review number sequences.
- Confirm SMTP invitation/reset delivery with approved recipients.
- Configure and accept Microsoft sign-in only when ready; retain intended MFA policy.
- Assign and complete an initial access review.
- Check health indicators and arrange scheduler/worker operation when required.
- Perform business acceptance testing before commissioning the deployment.

## Glossary

| Term | Meaning |
| --- | --- |
| Legal entity | Registered company/legal structure with its own registration details |
| Organization | Operating unit that owns memberships and business records |
| Department | Functional unit within an organization |
| Branch | Office or operational location |
| Team | Primary work group attached to an organization and optionally a department |
| Position | Job title/grade/description and structural reports-to position |
| Reporting manager | Person's line manager within an organization |
| Approval manager | Optional separate organizational approver; otherwise reporting manager is used |
| Cost centre | Organizational cost/revenue allocation unit; not an accounting ledger |
| Role | Named permission bundle assigned to a user |
| Direct permission | Permission assigned separately from roles |
| Scope | Boundary determining which records a user can access |
| MFA / TOTP | Additional time-based authenticator verification |
| Entra | Microsoft identity service used by the optional SSO integration |
| Delegation | Temporary authority to act for an eligible approval principal |
| Access review | Evidence-backed recertification of a snapshot of user privileges |
| Sequence | Rules used to allocate unique references for supported records |
| Audit | Recorded actor/action and available before/after evidence |

## Technical and commissioning notes

Operators, rather than everyday users, maintain `.env`, application deployment, database access and background services. Administrator account credentials remain in the database; connection secrets remain in environment configuration.

Current development choices are `IDENTITY_MFA_REQUIRED=false` and `ENTRA_ENABLED=false`. Already enrolled authenticators still protect sign-in. Microsoft connection IDs/secret, callback and SMTP settings are configurable in `.env`; clear Laravel's configuration cache after approved changes. Do not publish `.env` or put its secrets in documentation.

Run host scheduler/queue services when commissioning, verify real mail delivery and Microsoft tenant authentication, and arrange backups/restore checks and business acceptance. Application installation alone does not certify those operations. Existing credentials must be preserved; never run `migrate:fresh` against the existing installation.

Further implementation references: [installation review](../module-installation-review.md), [Module 01](../module-one.md), [Module 02](../module-two.md), [Module 03](../module-three.md), [Module 04](../module-four.md), [Module 05](../module-five.md), [Module 06](../module-six.md), [Module 07](../module-seven.md), and [module roadmap](module-roadmap.md).

**Guide maintenance:** update this guide and its roadmap when modules, labels, permissions, limits or external configuration change. It reflects repository behavior at the edition date, not a guarantee that all future specification requirements are implemented.
