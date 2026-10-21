# Top-Level Module 2 — CRM & Customer Management

**Status:** Implemented locally. This menu group consolidates the CRM and lead workflow pages. The older sequential feature catalogue calls the lead workflow Module 04; that existing numbering is retained as a requirements cross-reference.

## Menu and capabilities

| Menu item | What it provides |
| --- | --- |
| CRM Dashboard | Scoped lead totals, status counts, source distribution, recent leads and upcoming follow-ups |
| Leads | Capture, search, assignment, duplicate review, status, import and lead profile |
| Accounts | Organization accounts, primary contact details, owner and linked contacts |
| Contacts | People linked to accounts, contact channel preference and email/SMS opt-in indicators |
| Lead Sources | Built-in and organization-defined acquisition sources with usage counts |
| Qualification | Qualification work queue, score, budget, authority, need and timeline fields on the lead profile |
| Interactions | Calls, emails, meetings, notes and tasks linked to a lead |
| Follow-ups | Upcoming and overdue lead follow-ups |
| Segmentation | Save and revisit lead filters by status, source and industry |
| Conversion | Qualified and converted leads; conversion creates an account, contact and opportunity |
| CRM Reports | Scoped reports for source, status, ownership, ageing and conversion, with CSV export where permitted |

## Access and data boundaries

The CRM menu is visible to users with `leads.view`. Lead rows, dashboard totals, interactions, and reports use the existing CRM data-scope policy. Account and contact records are limited to the active organization; for own/team/department scopes, users see records they created plus records linked to leads in their permitted lead scope. Creating leads, accounts, contacts or saved segments requires lead creation access. Only superadmins and users with `leads.rules.manage` may add organization lead sources.

Contacts record channel preference plus email/SMS opt-in state. These fields are consent indicators for staff and do not themselves send messages. SMS sending still follows the SMS module's consent checks.

## Apply locally

From the ERP project directory:

```sh
php artisan migrate --path=database/migrations/2026_10_20_290000_complete_top_level_crm_module.php
php artisan optimize:clear
```

The migration adds account ownership, contact preferences, organization-scoped lead sources and saved segments. Existing lead sources and CRM records are retained. Give CRM users `leads.view`; add `leads.create`, `leads.update`, `leads.assign`, `leads.qualify`, `leads.convert`, `leads.reports.view`, `leads.export` and `leads.rules.manage` only to the roles that need those actions. The Administrator role has full access under the application's superadmin behavior.
