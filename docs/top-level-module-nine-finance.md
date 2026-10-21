# Top-level Module 9 — Finance

**Status:** Available locally in the ERP. This guide follows the supplied top-level menu. The older sequential catalogue calls the underlying finance feature set Module 16; see the [Finance operating guide](module-sixteen.md).

## Menu

Finance appears as a top-level sidebar group with these entries:

1. Dashboard
2. Invoices
3. Recurring Billing
4. Payments
5. Credit Notes
6. Expenses
7. Budgets
8. Revenue
9. Receivables
10. Reports

Tax Codes and Approval Limits are setup pages linked from the Finance dashboard. Access to each page and operation is permission checked; organization data is scoped to the active organization.

## Workflows

- **Invoices:** create a draft with up to 25 description, quantity, unit price and tax-code lines; submit it for approval; a different authorized user approves or rejects it; an authorized Finance user issues an approved invoice. Outstanding balance reflects posted payments and issued credit notes.
- **Recurring Billing:** configure weekly, monthly, quarterly or yearly schedules. The scheduler creates invoice drafts; staff still review, approve and issue each invoice.
- **Payments:** record a receipt against one invoice, then reconcile it against a statement reference. The workflow does not connect to a bank.
- **Credit Notes:** submit a credit against an invoice for independent approval and issue. Issued credits reduce the invoice balance.
- **Expenses:** employees submit claims; an authorized reviewer approves or rejects another user's claim; Finance marks approved claims reimbursed.
- **Budgets:** configure active amounts by period, currency, department or service and compare them with invoiced activity.
- **Revenue:** view billed invoice totals grouped by client, service and department for the current calendar year.
- **Receivables:** view open invoice balances and aging buckets, separated by currency.
- **Reports:** review revenue and receivables. Authorized users can export invoice data to CSV.

## Before operating

Configure organization membership, roles and finance permissions; tax codes; invoice number sequences; approver limits; and recurring-invoice scheduling. Finance should confirm applicable tax treatment, invoice and credit-note approval limits, retention rules, and accounting export requirements before production use.

## Scope and policy decisions

The installed feature set is an operational invoicing and finance register, not a general ledger. It does not perform double-entry accounting, bank-feed matching, foreign-exchange conversion, tax filing, invoice PDF/email delivery, refunds, or external accounting synchronization. Budget actuals use issued invoice revenue and do not include expenses. Revenue is billed value rather than accounting-recognized revenue. Recurring schedules create drafts, not automatically issued invoices.

The supplied decision register marks detailed validation, permissions, approval policy, statuses and exceptions, notifications, exports/integrations and acceptance criteria as requiring confirmation before build. Consequently, those organization-specific policy decisions remain commissioning work; they should not be assumed from the presence of the screens.
