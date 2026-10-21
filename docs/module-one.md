# Module 01 System Administration and Configuration

Scope follows module 01 in GWO_SEVO_ERP_Module_Feature_Breakdown.docx, with the authorization, audit, validation and operational conventions from the full specification. This is an additive implementation in the existing ERP. No fresh installation, database reset, account migration or credential reseeding is performed.

Open **Administration** from the signed-in navigation. Access requires an active user with the existing **Administrator** role. Every controller action and Livewire health action checks authorization. Settings are global to this ERP installation; CRM records retain their existing organization scope.

ERP forms share standard field, label, focus, validation and button styling. Their field arrangement remains specific to the task, and multi-column layouts adapt to narrow screens. Read the field labels and validation messages before saving configuration or reference data.

| Area | Implemented behavior |
| --- | --- |
| General | Company name, contact details, address, presentation time zone and date format |
| Branding | Validated private PNG/JPEG/WebP logo, login message, accent colour, shared branded document header/footer |
| Preferences | Currency code, financial year starting month, pagination applied to leads and reference data |
| Modules | CRM enable/disable enforced on all current lead routes and dashboard. Authentication, administration and organization foundations stay enabled. Unimplemented modules cannot be enabled. |
| Custom fields | Lead text, number, date and select fields; required/optional validation; labels/options and availability editable; immutable keys/types; disabled values retained |
| Reference data | Country, region with parent country code, industry and customer category catalogues; create, edit, deactivate |
| Numbering | Transactional, row-locked lead/customer allocation; configurable prefix, padding and increasing next number; collision checks; existing references unchanged |
| Import/export | Reference data only; CSV/XLSX mapping, full validation, preview and explicit commit; transactional updates; CSV/XLSX exports; branded browser print/PDF |
| Health | Database, private storage, cache, queue connection, scheduler and worker heartbeat; Livewire refresh and queue probe |
| Maintenance | Staff/guest 503 notice with Retry-After; administrator access and sign-in retained; operational notices |
| Audit | Before/after configuration, custom field, reference, numbering and module changes; imports, exports and health probes |

## Data and credentials

New tables: `system_settings`, `module_settings`, `custom_fields`, `reference_values`, `number_sequences`. Adds `leads.custom_values` and nullable unique `accounts.reference`. Existing records and references are preserved. User emails and password hashes remain in `users`; no administrator credentials are stored in `.env`, configuration, master-data exports or seeders. Database connection secrets continue to use Laravel's existing environment configuration.

The existing seeder creates roles, permissions and organization foundations only. It never creates users or resets passwords. Do not run `migrate:fresh` against this installation.

Lead and customer references now use their configured sequences. Ticket, quotation and invoice sequences are reserved for future modules. Currency and financial year settings are available for future finance/reporting consumers; this module does not introduce financial accounting. Identity is delivered in Module 02 (see module-two.md). Organization management is delivered in Module 03 (see module-three.md).

## Import contract

CSV is comma-delimited UTF-8 with a header. Excel imports accept `.xlsx` values from the first worksheet resolved through workbook relationships. Formulas, macros, legacy `.xls`, workbooks exceeding 20 MB expanded, files over 2 MB and batches over 1,000 rows are unsupported. Up to 50 distinct nonempty column headings are permitted. Map required category/code/label fields and optional parent_code/is_active. Category is `country`, `region`, `industry` or `customer_category`; codes use uppercase letters, digits, underscores or hyphens. Regions require an existing country or a country in the same import. Active values are `1` or `0`; default is `1`.

Category/code pairs identify updates. An invalid row prevents the complete import. Upload/preview state expires after 30 minutes and is bound to the signed-in user. Preview is cleared before remapping. Repeated commit is rejected after success. XLSX exports use text cells, and CSV exports neutralize spreadsheet formula prefixes. Export and import support master reference data, not users or arbitrary database tables. The PDF path uses the browser's Print / Save as PDF; it is not a server-side PDF renderer.

## Operations

Apply the additive migration with `php artisan migrate --force`. Assets are built during development using `npm run build` and shipped in `public/build`; no production npm process is needed. Module-specific CSS is in `public/css/administration.css`.

Run the Laravel scheduler once a minute in the deployment environment. The `erp-health-heartbeat` scheduled task records a scheduler heartbeat and dispatches a queue heartbeat. Use the configured queue worker/Horizon for asynchronous queues. The health screen reports absent or stale heartbeats and does not claim a sync queue has a running worker. This implementation does not install host cron or supervisor services.

Configuration uses a request-scoped settings reader, so saves take effect on subsequent requests without a long-lived settings cache. Application/database timestamps remain UTC; the selected time zone controls display. Livewire 3 script/update URLs preserve the detected Laravel base path. The production application is served at <https://erp.gwosevo.com>.

## Verification and UAT

Automated feature coverage includes admin access, settings validation/audits, branding upload/removal, all admin screens, maintenance recovery, module guards, number sequencing, custom field persistence/validation, reference integrity, mapped import preview/commit, failed import isolation, CSV/XLSX export and round-trip, Livewire authorization/probes, and credential preservation on reseeding.

Administrator acceptance flow:

1. Sign in with the existing account; open Administration and save company/date preferences.
2. Upload a logo, change the login message, inspect login and a branded reference printout.
3. Add country `GH`, then a region referencing `GH`; import/export a small approved reference file.
4. Add a required lead select field and confirm a lead cannot be saved without it.
5. Change the lead prefix and confirm a new lead uses it while existing leads keep their references.
6. Disable CRM and confirm its navigation and direct routes are unavailable; re-enable it.
7. Enable maintenance, confirm staff receive 503, then disable it through the administrator account.
8. Inspect health checks and audit history; verify scheduler/worker heartbeats after those host services run.

Production infrastructure setup, full backup/restore verification and business UAT sign-off remain separate operational release steps from the specification.
