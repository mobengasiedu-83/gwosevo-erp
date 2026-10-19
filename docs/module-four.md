# Module 04 CRM Lead Management

## Installed capabilities

The CRM module extends the original lead foundation with:

- Signed website and Sanctum API capture, idempotency keys and request throttling.
- Preview-and-confirm UTF-8 CSV import, row validation, source attribution and duplicate flags.
- Organization-scoped duplicate candidates matched by normalized phone, case-insensitive email and company name.
- Fit, engagement and weighted total scoring. Defaults are 60% fit and 40% engagement; weights are configurable with `CRM_FIT_WEIGHT` and `CRM_ENGAGEMENT_WEIGHT`.
- Source/industry assignment rules and a locked per-organization round-robin cursor for unmatched leads.
- Activity tasks, due-date reminders, lead-level next-follow-up reminders and supervisor escalation.
- Lead disqualification, archive/restore, conversion, audit history, scoped reporting and CSV report export.

## Access

Permissions added by `IdentitySeeder`:

- `leads.disqualify`
- `leads.import`
- `leads.reports.view`
- `leads.rules.manage`
- `leads.duplicate.review`

The existing `leads.archive` and `leads.export` permissions are used for archive/restore and report export. Administrators receive the new permissions when `IdentitySeeder` runs. Other roles must be granted only the capabilities their work requires.

Lead queries and duplicate candidate lists enforce organization and own/team/department/organization scope. Public website requests have no user actor; their audit event identifies website capture through the lead source and has a null actor. Website and API capture keys are persisted per organization, while only payload hashes are stored.

## Capture contracts

`POST /api/v1/leads/capture/{organization-code}` expects JSON with `first_name`, `last_name` and optional `email`, `phone`, `company_name`, `industry`, `interests`, and `source_detail`. `X-CRM-Signature` is a lowercase or uppercase hex HMAC-SHA256 of the exact raw request body using `CRM_WEBSITE_CAPTURE_SECRET`. Include an `Idempotency-Key` between 8 and 128 permitted characters. The endpoint returns HTTP 202 with the lead reference.

`POST /api/v1/leads` uses Sanctum authentication, the user's active organization and `leads.create`. It requires the same idempotency header and returns the lead ID, reference and status. A reused key with a different validated payload returns HTTP 409. Both endpoints reject disabled/inactive organizations and use the `crm-lead-capture` per-IP limiter.

The website secret must stay server side. A public browser form should post to the organization's own backend, which validates the form and signs the request to the ERP.

## Import contract

CSV headers are case-insensitive. Required headers: `first_name`, `last_name`. Optional headers: `email`, `phone`, `company_name`, `industry`, `interests`. Unsupported or duplicate headers reject the upload. Up to 4 MB and 1,000 data rows are accepted. Invalid rows are displayed and omitted from the valid preview; the user explicitly confirms the valid rows before a single transactional import.

## Follow-up operations

`crm:send-follow-up-alerts` runs every 15 minutes through Laravel Scheduler. It sends database notifications to a lead owner or authorized creator, then escalates after `CRM_FOLLOW_UP_ESCALATION_HOURS` (default 24) to an authorized reporting manager or Administrator. Due tasks on closed or archived leads are ignored. Keep the Laravel scheduler running in production.

## Data model and install

The additive migration adds scoring, normalized phone, duplicate review, disqualification, archive and follow-up delivery fields; creates assignment rule/cursor and capture idempotency tables; and creates the Laravel notifications table only when one does not exist. Existing lead data is retained, and phone normalization is backfilled. No new users or fabricated organization data are seeded.

Install with:

```bash
php artisan migrate --force
php artisan db:seed --class=IdentitySeeder --force
```

Do not run `migrate:fresh`. Website capture stays disabled until `CRM_WEBSITE_CAPTURE_SECRET` is configured. Clear Laravel's config cache after environment changes. Ensure the scheduler is active for reminders and escalations.
