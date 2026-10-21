# Module 02 Identity Users Roles and Permissions

Implements catalogue module 02 in the existing Laravel/Blade ERP. The existing superadmin email, password hash, active state and organization assignment are preserved during installation. No administrator credentials are stored in `.env`, seeders or configuration.

## Installed capabilities

- User invitations with hashed one-time 48-hour tokens, resend/revocation, activation by password setup, suspension and deactivation. Administrative access changes revoke sessions and API tokens. Email and password of an existing account are not editable on the user-management screen.
- Protected Administrator recovery role, built-in Sales/Operations/Human Resources/Finance/Support/Read Only roles, configurable business roles, direct permissions and per-role MFA requirements. Unused custom roles may be deleted; protected built-ins may not. The last active administrator cannot be removed or deactivated. Users cannot demote/deactivate themselves through administration.
- View/create/update/archive/export/approve/assign permissions plus existing CRM qualify/convert actions. Permission definitions do not create additional CRM business workflows; archive/export/approval consumers remain part of their respective business modules.
- Own/team/department/organization membership scopes, validated department/team relationships, scoped CRM lists, dashboard metrics and record policies, scoped owner choices and authorized organization switching. Lead ownership defines own scope; unassigned records use their creator. Delegation never broadens record visibility.
- Local login with per-account/IP throttling, reset requests with a uniform response, hashed single-use reset tokens, password complexity validation and session invalidation after password changes/resets.
- TOTP authenticator enrollment with proof of possession, encrypted secrets, one-time hashed recovery codes, replay prevention, rate-limited challenges and required enrollment for administrators, approval holders and marked privileged roles/users. Password resets do not remove MFA. Another administrator may reset a lost authenticator after confirming their own password and MFA and recording identity-verification evidence. Reset forces reenrollment and revokes sessions; it never changes the user password.
- Driver-independent session registry using hashed session identifiers, device/IP/time metadata, individual revocation, all-session revocation via authentication versioning, and remember-cookie renewal through the MFA flow. Suspended/inactive accounts are rejected on existing browser sessions and API access. API tokens are deleted when user access is revoked.
- Microsoft Entra single-tenant authorization-code flow with PKCE, state, nonce, expiry, signature, issuer, tenant, audience and object-ID verification. Explicit tenant/object-ID linking only; no email-based automatic linking or automatic user creation. Local MFA remains enforced on Microsoft sign-in.
- Time-limited, revocable approval delegation with organization, principal permission, data-scope and active-user checks. Installed `ApprovalAuthority` resolves the accountable principal for approval workflows; it does not grant general permissions or introduce CRM approval business steps.
- Periodic access-review snapshots of roles, direct/effective permissions and memberships; assigned-reviewer decisions; stale-snapshot rejection; administrator completion with transactional revocations; completed-review locking. `identity:review-due` checks daily and creates the next review when the configured interval is due and no review is open.
- Authentication, access-view, user/role/scope, delegation, review, MFA and session audit history. Passwords, MFA secrets, recovery codes, raw session IDs and invitation/reset tokens are excluded from audit metadata and flashed request input.

## Navigation

Use **Users & access** for administration and **My security** for personal MFA, password and session controls. Access-review assignments and approval delegations also appear on the dashboard. Only active administrators can manage users, roles, audit activity or integration configuration status. Reviewers may inspect only reviews assigned to them (or all reviews when they are administrators).

When mandatory MFA is enabled, unenrolled administrators must enroll an authenticator. Mandatory enrollment is currently disabled by user choice. Use the unchanged password to generate the setup key, add it to an authenticator app and confirm a six-digit code. Save the eight recovery codes when displayed; they are shown once. Manual authenticator setup avoids sending any secret to a third-party QR service.

## Environment configuration

All Microsoft and SMTP connection settings are in `.env`, as requested. `.env.example` documents the available variables without real credentials. Clear Laravel's configuration cache after changing them.

| Variable | Purpose |
| --- | --- |
| ENTRA_ENABLED | Enable Microsoft sign-in after setup; currently false by user choice |
| ENTRA_TENANT_ID | Entra directory UUID |
| ENTRA_CLIENT_ID | Entra application UUID |
| ENTRA_CLIENT_SECRET | Secret entered locally by the operator; not returned by any UI |
| ENTRA_REDIRECT_URI | Exact HTTPS Web callback, matching APP_URL plus /auth/microsoft/callback |
| MAIL_MAILER | smtp |
| MAIL_SCHEME | smtp for the supplied relay; smtps where configured by the operator |
| MAIL_HOST / MAIL_PORT | Relay address and port |
| MAIL_USERNAME / MAIL_PASSWORD | null for the supplied unauthenticated relay |
| MAIL_AUTO_TLS | false for the supplied relay; configurable |
| MAIL_TIMEOUT | Connection timeout, initially 10 seconds |
| MAIL_FROM_ADDRESS / MAIL_FROM_NAME | Configured sender identity |
| IDENTITY_REVIEW_INTERVAL_DAYS | Recertification interval, default 90, bounded 7–365 |

The supplied tenant/client identifiers and unauthenticated SMTP relay configuration have been applied. Microsoft sign-in remains disabled until the client secret and exact redirect registration are configured. Linking a user requires their Entra **Object ID**, not their application/client ID or email. No live Microsoft login can be certified before those external configuration steps and a real tenant sign-in.

The production login and invitation/reset pages are served at <https://erp.gwosevo.com>. Keep `APP_URL`, `ENTRA_REDIRECT_URI` and the corresponding Microsoft Entra Web callback aligned with this domain; update all three together if the domain changes.

Invitation delivery is synchronous so errors are visible and resending is possible. A failed delivery leaves the account invited but revokes the attempted token. Log/array transports are blocked for real invitation/reset delivery to avoid writing credential-setting links into logs. Tests use notification fakes. No test mail is sent to real users during installation.

## Data and operations

Migration adds user MFA/identity/session-version fields, per-role MFA requirements, `user_invitations`, `identity_sessions`, `login_events`, `approval_delegations`, `access_reviews`, and `access_review_items`. Existing users, passwords, roles and organization memberships are retained. The idempotent IdentitySeeder registers permissions/roles without creating users or resetting configured business-role permissions.

Run `php artisan migrate --force`, then `php artisan db:seed --class=IdentitySeeder --force` to apply this module to an existing installation. Do not run `migrate:fresh`. Ship the built assets. Continue to run the existing scheduler; it now also checks for periodic access reviews. Session revocation is enforced through the registry/version regardless of Redis, database or file session storage.

New dependencies are pinned in composer.lock: PragmaRX Google2FA for TOTP and Firebase PHP-JWT 7 for signed OIDC token verification. The older JWT 6 dependency was upgraded after Composer reported an advisory; the resulting dependency audit is clean.

Audit tables are append-only through the application UI; infrastructure-level tamper evidence, retention enforcement and backup verification belong to the later Security/Audit/Backup module. Production security review, real SMTP delivery acceptance and Microsoft tenant sign-in acceptance remain deployment/UAT activities, not claims inferred from automated tests.

## Installation verification

The additive migration and IdentitySeeder were applied to local MySQL. A before/after comparison verified existing emails, password hashes, statuses, roles and organization memberships are unchanged. The automated suite passes 48 tests with 306 assertions. Assets build successfully and coding-style checks pass. SMTP connection/EHLO/NOOP succeeded without sending mail. Microsoft sign-in remains disabled by user choice; external tenant authentication and email delivery acceptance are not claimed as tested.

## Mandatory MFA enrollment switch

`IDENTITY_MFA_REQUIRED=false` disables mandatory enrollment for unenrolled administrators, privileged roles and marked users. It is currently false by user request. Set it to true and run `php artisan config:clear` to require enrollment again. MFA setup remains available under My security. Already enrolled accounts continue to require their authenticator; this switch does not erase secrets, recovery codes, passwords or user-level requirements.
