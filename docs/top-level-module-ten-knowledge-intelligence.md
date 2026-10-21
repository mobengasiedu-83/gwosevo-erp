# Top-level Module 10 — Knowledge & Intelligence

**Status:** Available locally. This guide follows the supplied top-level menu. Its knowledge repository and reporting components are cross-referenced as older sequential Modules 18 and 19 in the [user guide](user-guide/README.md).

## Menu

The sidebar groups the approved eight entries under Knowledge & Intelligence:

1. Knowledge Bank
2. Documents
3. AI Search
4. Sources
5. Executive Dashboards
6. Custom Reports
7. Scheduled Reports
8. Exports

Entries appear according to module availability and the signed-in user's permissions. Documents require document-view permission; Sources require source-sync management access. Report pages require dashboard, report, KPI, schedule or relevant source permissions.

## Current workflows

- **Knowledge Bank:** create scoped knowledge spaces, categories and article drafts; submit articles for independent review and publication; search published content in spaces available to the user. Article revisions are versioned.
- **Documents:** browse private files attached to accessible articles and download only after server-side organization, space and document permission checks. Uploads are attached to draft articles and accepted types are PDF, DOC, DOCX and TXT.
- **AI Search:** use keyword search even when the AI provider is not configured. When the provider and `knowledge.ai.ask` permission are enabled, ask questions over authorized published sources; answers include source links. Search falls back to keyword retrieval if semantic indexing is unavailable.
- **Sources:** manage Microsoft 365 SharePoint folder mappings and manually run sync. Synced files become drafts for local review; sync activity is recorded. Configure the Entra app and `KNOWLEDGE_GRAPH_*` settings before connecting.
- **Executive Dashboards:** view only the business indicators and approved KPIs allowed by the user's data-source permissions; select a reporting period and print/save the page as PDF.
- **Custom Reports:** create, run and save reports using approved data sources, fields, filters, grouping and measures.
- **Scheduled Reports:** save and pause report schedules and list authorized recipient user IDs. The schedule is setup metadata only; automated report generation and delivery are inactive.
- **Exports:** download authorized saved reports as CSV. Source-level report permissions still apply.

## Commissioning limits and decisions

The supplied decision register confirms these menu entries but labels many feature details “Confirm before build.” Confirm the business owner, role matrix, required data, retention, notification behavior, export limits, integrations and acceptance criteria with the relevant owners before production use.

Current implementation boundaries include:

- Document folders, controlled document-specific version history, configurable retention, and generalized source connectors are not available. Article version history and private article attachments are available.
- Microsoft 365 SharePoint is the only knowledge source connector implemented. Other external sources require separate integration work and authorization.
- AI answers depend on an externally configured provider and key. The ERP does not supply the AI service, and answers are limited to content retrieved from spaces the user can access.
- Executive indicators and custom report datasets are limited to the configured report catalogue and permissions. This does not replace a separately governed BI warehouse.
- Scheduled report definitions are not automatically executed or emailed. Report delivery needs explicit approval of recipients, channel, content scope, cadence and failure handling because reports may contain confidential cross-module data.
- Exports are CSV. Native XLSX, broad record exports and a dedicated export-audit dashboard are not part of the current workflow.
