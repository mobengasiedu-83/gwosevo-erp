# GWO SEVO ERP — User Guide

**Edition:** 17 October 2026 · **Audience:** staff, supervisors, reviewers and administrators  
**Application:** <https://localhost/gwosevo-erp/public/>

This guide describes the application currently installed in this repository. Modules 01–03 are installed. Module 04 has a working lead-management foundation, not its complete specification. Modules 05–22 are planned and have no operational user screens yet. Their feature catalogue is included in the [module roadmap](module-roadmap.md); those descriptions are requirements, not instructions for functions already available.

The names “superadmin” and “Administrator” refer here to the existing Administrator account/role. This guide contains no account passwords, invitation tokens or integration secrets.

## Contents

1. [Getting started and navigation](#getting-started-and-navigation)
2. [Roles and access](#roles-and-access)
3. [Module 01 — System administration and configuration](#module-01--system-administration-and-configuration)
4. [Module 02 — Identity, users, roles and permissions](#module-02--identity-users-roles-and-permissions)
5. [Module 03 — Organization, departments and branches](#module-03--organization-departments-and-branches)
6. [Module 04 — Current CRM lead workflows](#module-04--current-crm-lead-workflows)
7. [Modules 05–22 — Planned capabilities](#modules-0522--planned-capabilities)
8. [Daily and periodic routines](#daily-and-periodic-routines)
9. [Troubleshooting](#troubleshooting)
10. [Administrator setup checklist](#administrator-setup-checklist)
11. [Glossary](#glossary)
12. [Technical and commissioning notes](#technical-and-commissioning-notes)

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

## Module 04 — Current CRM lead workflows

**Status:** available lead foundation; the complete Module 04 specification is not yet installed. **Open:** Commercial → **CRM leads**. Required permissions and record scope apply to each action.

### Capture and find a lead

1. Open the creation action on the lead list or **Capture a lead** on the dashboard.
2. Enter required first/last name and lead source.
3. Add email, phone, company, industry, interests, next follow-up and required custom fields as applicable.
4. Save. The application generates a lead reference and marks it new.
5. Use list search for first name, last name or company, then open the lead.

Available source labels include manual, website, API, referral, inbound call and event. Selecting a source records a classification; it does not install website capture, an ingestion API or a telephone integration.

### Edit and assign

Open an accessible lead and choose its edit action, change details and save. Use the owner-assignment form to select an eligible active owner and submit. Assigning a new lead changes it to assigned; assignment does not reset an already qualified/converted status. The lead's organization and your scope still control visibility.

### Record activities and follow-ups

On the lead detail page, add a call, meeting, note or task with a subject, optional body and due date. Review the activity history after saving. Maintain **Next follow-up** on the lead record for the dashboard's follow-up list. An activity's due date and the lead's next-follow-up date are separate fields; creating an activity does not automatically update the latter.

### Qualify

Record budget, authority, need, timeline and a score from 0 to 100 using the qualification form. Submit to mark the lead qualified and record its status history. Converted/disqualified leads cannot be qualified through this action. Scoring is manually entered in this foundation; there is no automatic engagement-scoring engine yet.

### Convert

1. Confirm the lead is qualified and company/contact details are accurate.
2. Use **Convert** when you have conversion permission.
3. Review the converted status and generated commercial references/details.

Conversion creates or reuses the organization/company account by name, creates a contact and opportunity, and links them to the lead. Only qualified leads can be converted. These linked records are a commercial foundation, not a full sales-pipeline or customer-management workspace.

### Current limits

Rules-based/round-robin assignment, approved lead imports, duplicate-detection workflows, automatic scoring, escalation, comprehensive CRM reporting and external capture integrations are not supplied by the current foundation. Module 01 reference import cannot be used to import leads. There is no supported lead-archive/delete action in the current UI. See [Module 04's planned feature catalogue](module-roadmap.md#module-04-crm--lead-management).

## Modules 05–22 — Planned capabilities

These modules do not yet have operational screens in this installation. Do not expect an icon, permission or reserved sequence to enable their workflows. The [complete module roadmap](module-roadmap.md) lists every specified module and feature, including the distinction between current foundations and future work.

| Module | Planned area |
| --- | --- |
| 05 | Sales pipeline and opportunities |
| 06 | Quotations, contracts and client onboarding |
| 07 | Contact centre and VitalPBX |
| 08 | Customer support and ticketing |
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

Detailed operational procedures for these modules must be written against their implemented screens and tested behavior when installed. The roadmap is not an instruction to activate or implement them now.

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

Further implementation references: [installation review](../module-installation-review.md), [Module 01](../module-one.md), [Module 02](../module-two.md), [Module 03](../module-three.md), and [module roadmap](module-roadmap.md).

**Guide maintenance:** update this guide and its roadmap when modules, labels, permissions, limits or external configuration change. It reflects repository behavior at the edition date, not a guarantee that all future specification requirements are implemented.
