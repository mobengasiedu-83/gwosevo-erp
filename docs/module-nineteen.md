# Module 19 — Reports, Dashboards & Business Intelligence

Open Workspace → **Reports & Insights**. The module adds organization-scoped summary cards, KPI definitions, custom grouped/detail reports, CSV exports, browser print output, and schedule configuration.

## Install and grant access

Apply the migration and register permissions:

```sh
php artisan migrate --force
php artisan db:seed --class=IdentitySeeder --force
php artisan optimize:clear
```

The existing Administrator/superadmin can open the hub and manage reports, KPIs and schedule definitions. Source data is filtered by the active organization and each source's existing permission. In **Administration → Roles & permissions**, assign only the source report permissions the recipient needs:

| Source | Permission |
| --- | --- |
| CRM leads | `leads.reports.view` |
| Sales opportunities | `opportunities.reports.view` |
| Contact centre calls | `calls.view` |
| Support tickets | `tickets.reports` |
| Workforce | `hr.reports.view` |
| Leave requests | `attendance.reports.view` |
| Recruitment | `recruitment.reports.view` |
| Performance reviews | `performance.reports.view` |
| Finance invoices | `finance.reports.view` |
| Projects and tasks | `projects.reports.view` |

The extra permissions `reports.dashboard.view`, `reports.custom.manage`, `reports.kpis.manage`, and `reports.schedules.manage` control hub access and administration. The superadmin can assign these separately. Do not give a role broad report access without reviewing its data scope.

## Use the reports

Choose a date range at the top of the page. Summary cards count records for each permitted source in that range. Create a KPI using a source, an available measure (for example `count`, `sum:amount`, or `avg:duration_seconds`), optional filter and target. KPI values recalculate for the selected period; the target progress is capped at 100%.

For custom reports, select a permitted source, an optional grouping, a measure, optional detail columns and filters, then save. Column, group and measure names must match the source guide shown under the form. Reports are limited to 500 groups or 1,000 detail rows. Export CSV for spreadsheet analysis. Use **Print / save PDF** and the browser's Save as PDF option for a printable report.

Schedule setup records a frequency and active recipient IDs from the current organization. **Automated scheduled CSV email delivery is not active.** The system does not send report payloads to email until that outbound data flow is explicitly approved and implemented.

## Data and limits

- Reports read current organization records; they are not historical snapshots and do not provide point-in-time trend history.
- Each source has its own permission gate. A user must also remain an active member of the active organization.
- CSV is supported and opens in Excel; native `.xlsx` output is not implemented.
- PDF output uses browser print, not a generated PDF service.
- KPI calculations and custom reports use approved source fields; they do not execute user-authored SQL or formulas. Finance invoice totals must be grouped by currency or filtered to one currency before summing.
- Forecasting is limited to the existing sales forecast functionality. This module does not create predictive forecasts.
- Scheduled delivery definitions can be managed or paused, but there is no automated delivery command in this installation.

For production, review role permissions, set the active organization correctly, and configure the Laravel scheduler only for the application's enabled operational commands. See also the [module roadmap](user-guide/module-roadmap.md).
