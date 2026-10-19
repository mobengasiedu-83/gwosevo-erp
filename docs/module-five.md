# Module 05 — Sales Pipeline & Opportunities

**Status:** Installed, with master-data commissioning required. See the [user guide](user-guide/README.md#module-05--sales-pipeline-and-opportunities).

## Installed features

- Opportunity creation, references, account/contact association, owner, value and expected close date.
- Kanban and paginated list views across the configured sales stages.
- Organization-scoped stage labels, ordering and probability. The default stages are New, Contacted, Qualified, Needs Assessment, Proposal/Quotation Sent, Negotiation, Awaiting Customer Decision, Won, Lost and On Hold.
- Strategic service line and integrated service package catalog mapping.
- Calls, meetings, notes, due tasks, dashboard reminders, stage history and audit events.
- Competitor names and win/loss reason recording.
- Discount and nonstandard-terms approvals. Requesters cannot decide their own requests; a pending request blocks Won, and nonstandard terms/declined requests require an approved request before Won.
- Person/team sales targets, weighted forecast, win rate, sales-cycle average, monthly forecast-accuracy measurement and scoped CSV reporting.
- Lead conversion integration: qualified CRM leads create a referenced opportunity in New.

## Commissioning

1. Sign in as Administrator and confirm **System configuration → Modules → Sales Pipeline & Opportunities** is enabled.
2. Open Commercial → **Sales pipeline → Pipeline setup**.
3. Enter the four approved strategic service line names and seven approved integrated service package names. The supplied ERP documents specify the counts, not the names. The application intentionally does not invent the company's offerings.
4. Review stage probabilities and display ordering with Sales leadership.
5. Configure target periods and amounts under **Sales targets**.
6. Review Administrator, Sales, Finance and Read Only roles plus each user's organization/data scope. The seeder adds Module 5 permissions to those built-in roles without removing their other permissions.
7. Run the host Laravel scheduler. It captures forecast snapshots weekly and sends opportunity task reminders every 15 minutes.
8. Approve the discount/nonstandard-terms workflow and complete user acceptance with business owners before relying on forecasts or commercial approvals.

Forecast accuracy compares each completed month's last pre-month weighted snapshot with Won revenue entered for that month. It remains **Collecting** until a qualifying snapshot and completed month exist. Accuracy is not backfilled for periods before this installation.

## Records and boundaries

The additive migration preserves existing opportunities, assigns references and writes initial stage history. It enables Module 5 and does not seed fictitious company service names, quotas, competitors, approval limits or deal data. Module 6 still owns quotations, contracts and onboarding; this module does not create a delivery project. Finance amounts use the existing application currency setting; no conversion or tax calculation occurs here.

Opportunity actions and reports use organization membership and own/team/department/organization scope. The Finance role can review approvals where its record scope allows. Administrators can configure stages, catalog and targets.
