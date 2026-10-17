# Modules 01, 02 and 03 installation review

Reviewed 17 October 2026 against the two supplied ERP specifications. All three modules are installed in the existing Laravel application and have working screens, authorization, validation, migrations and automated coverage. They should not be described as fully commissioned production services: Microsoft SSO is intentionally disabled, and continuous host scheduler/worker operation requires separate operational setup and verification.

## Module 01 System Administration and Configuration

| Requirement | Installed implementation / boundary |
| --- | --- |
| General settings | Company profile, contact details, time zone and date formats |
| Branding | Official GWO SEVO default assets, configurable custom logo/accent/login message, shared login and document branding |
| Preferences | Currency, financial year month and pagination |
| Module management | Controlled CRM enablement, protected core dependencies, future modules explicitly unavailable |
| Custom fields | Validated lead fields; disabled field values retained; other record types belong to future modules |
| Reference data | Countries, regions with parent country, industries and customer categories |
| Numbering | Locked lead/customer allocation; ticket/quotation/invoice definitions reserved for their business modules |
| Import/export | Validated mapped CSV/XLSX reference-data import with preview/commit; controlled exports; branded browser print/PDF |
| Health | Database, private storage, cache, queue connection and scheduler/worker heartbeat checks |
| Maintenance | Staff service notice, administrator recovery access, operational notices |

## Module 02 Identity Users Roles and Permissions

| Requirement | Installed implementation / boundary |
| --- | --- |
| Users | Invitations, one-time activation, suspension/deactivation, invitation resend, safe delivery-failure recovery |
| Roles / permissions | Protected built-ins, business roles, direct permissions, last-admin safeguards and configurable MFA requirements |
| Data scope | Own/team/department/organization scope in current CRM queries and policies; validated memberships and organization switching |
| Authentication | Local login, reset/change password, TOTP enrollment/challenge/recovery, throttling and session/token revocation |
| MFA enforcement | `IDENTITY_MFA_REQUIRED=false` by user choice; enrolled authenticators still protect sign-in |
| Microsoft SSO | Signed single-tenant OIDC/PKCE implementation and explicit object-ID linking; `ENTRA_ENABLED=false` by user choice; client secret/registration and real tenant acceptance remain outstanding |
| Delegation | Revocable time-limited delegation and scope-aware approval-authority service; approval business steps belong to their business modules |
| Access reviews | Periodic review command, snapshots, assigned decisions, transactional revocation, stale-snapshot checks and locked completion; daily execution depends on the host scheduler |
| Audit | Login attempts, access, configuration/role/scope changes, reviews, delegations, MFA and session changes; infrastructure-level tamper evidence belongs to Module 22 |

## Module 03 Organization, Departments & Branches

Installed on 17 October 2026. Legal entities, operating organizations, departments, branches, teams, positions, reporting/approval lines, cost centres and expandable organization charts are available through Organization structure. Management is restricted to Administrators; staff views are constrained to actual active organization memberships. Cross-organization assignments, cycles, invalid cost splits and orphaning deactivations/removals are rejected. See [Module 03](module-three.md) for the full requirement mapping and setup workflow.

The additive migration was applied to local MySQL. Before/after account fingerprints verified unchanged passwords, account status, roles, MFA data and existing membership assignments. The application does not fabricate legal registration or organization data.

## Findings corrected during this review

- XLSX import now resolves the first worksheet through workbook relationships, rather than assuming `sheet1.xml`.
- A failed invitation delivery opens the created account's management screen for resending, rather than returning to a creation form that would hit a duplicate email.
- User-membership selections and MFA selection persist after validation failure; department/team options compare numeric IDs consistently.
- Official branding is shared by signed-in pages, login/reset/invitation/MFA/maintenance pages and reference print output.
- Shared responsive navigation exposes every installed admin/identity workflow, marks the current section, and limits quick-navigation links to the user's role and enabled modules.
- Session revocation, role deletion, delegation revocation and review completion include accessible confirmation dialogs.
- Dashboard totals, pipeline, follow-ups, reviews and activity use real authorized records; no placeholder business figures are shown.

## Visual system

The public gwosevo.com stylesheet defines green `#449E37`, red `#DB0D1B`, yellow `#F4D80A`, charcoal `#231F20`, Poppins headings and Inter body type. The ERP applies these tokens with locally hosted official logo/hero imagery and fonts, responsive sidebar/topbar navigation, search-driven quick navigation, account controls, readable forms/tables, empty/error/success states and subtle motion. Reduced-motion preferences disable animation. Mobile navigation supports keyboard dismissal and focus containment; dialogs use native focus management.

Assets are sourced from the GWO SEVO public website and Google Fonts. Font license notices are stored beside the fonts. Refer to `public/images/brand/SOURCES.md` for asset provenance.

## Verification limits

Automated feature tests validate authorization and workflows using an isolated SQLite test database. Browser inspection uses a separate synthetic preview database, so it cannot alter real users or send mail. The local ServBay installation receives the built application assets. No administrator email/password, MFA flag, Entra enablement or mail-server settings are changed by this redesign.

SMTP connectivity was previously checked without sending mail; real invitation/reset delivery acceptance remains an operator/UAT step. Browser PDF export is intentional and is not a server-side PDF generator. Ticket/quotation/invoice numbering and non-lead custom fields cannot be exercised end-to-end until their corresponding business modules exist.

## Local operational findings

The local database review confirmed every listed Module 01/02 table is present. Database, storage, cache and queue connection checks were healthy, with zero pending jobs. Neither the scheduler nor the queue worker had a recorded heartbeat. Both `IDENTITY_MFA_REQUIRED` and `ENTRA_ENABLED` remain false, and the mail transport is SMTP. Effective default branding is GWO SEVO green `#449E37`. These observations confirm installation, not continuous worker execution or live Microsoft authentication.

## Final validation

The automated suite passes 67 tests with 392 assertions. Desktop and 390-pixel mobile browser inspection covered the dashboard, settings, users, roles, security, reference import, lead detail, health and login. Real synthetic login/logout, quick-navigation filtering, password visibility, mobile navigation, membership toggles, reduced motion and destructive-action cancellation were exercised. No JavaScript page errors or horizontal document overflow were found. Brand fonts/images are local, and the production asset build and PHP style checks pass. Error pages for forbidden, missing, expired-session, rate-limited and unavailable requests share the brand and do not depend on database settings to render.

Module Three browser verification also covered the organization list, people/chart, department catalogue, position form, membership assignment and legal-entity catalogue on desktop, with organization/chart/position/assignment screens at 390 pixels. No JavaScript errors or horizontal document overflow were found. The final production asset build passes.
