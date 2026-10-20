# Module 16 — Finance, invoicing and revenue

**Status:** Installed locally. Open Finance → **Finance & billing**. Records belong to the active organization. Give people the Finance role or the specific finance permissions they need; requesters cannot approve their own invoices, credit notes or expense claims.

## Billing workflow

1. Set up organization tax codes and optional role/currency approval limits.
2. Create an invoice draft with customer details, invoice and due dates, currency, service/department and line items.
3. Submit the draft for approval. A different authorized person reviews it; configured limits constrain invoice approvals.
4. Issue an approved invoice to begin receivables tracking.
5. Record a payment against the invoice and include its bank/receipt reference. Payment is allocated to that invoice, and its status updates from issued to partially paid or paid.
6. Compare the receipt against the bank or payment statement, then mark the payment reconciled and record the statement reference.
7. For an adjustment, submit a credit note against the unpaid balance. Another authorized reviewer can approve and issue it.

The invoice page has one line by default. Choose **Add invoice line** for up to 25 lines. Quantities and unit prices are decimal values. Active tax codes apply a percentage rate per line; choose whether tax is included in the line price or added on top. Configure codes and rates with Finance before creating invoices.

## Expenses and budgets

Employees submit expense claims for their own organization. Finance reviewers can approve or reject another person's claim; an approved claim can be marked reimbursed after payment occurs. The screen records reimbursement status but does not transfer funds.

Create a dated budget for a currency and optionally a service or department. Variance compares the budget with issued invoice revenue during that budget period. The current actual does not include expense claims. Revenue reporting groups issued invoice totals for the current calendar year by customer, service and department.

## Recurring billing

Create a schedule with customer, line description, amount, currency, optional tax code, frequency and next invoice date. Due schedules are processed by `finance:generate-recurring`, scheduled daily. Each generated invoice enters independent approval; it is not issued or emailed automatically. The scheduler must be configured on the host. Run the command manually to process due records or verify scheduler configuration. A schedule is deactivated after its final date or when its creator account no longer exists.

## Exports and controls

Use **Export invoices CSV** for an organization-scoped list containing invoice totals, payments, credits, outstanding balances and status. Reconciliation marking is manual; the module does not ingest bank files or match statement transactions automatically.

Invoice references use the existing invoice number sequence. Credit notes and expenses use `CRN-` and `EXP-` sequences. Review and adjust those sequences in Administration → System configuration → Numbering before using the module, while preserving references already assigned.

## Scope and production boundary

This module is an operational finance register, not a general ledger. It does not provide double-entry postings, bank-feed reconciliation, foreign-exchange conversion, tax filing, invoice email/PDF delivery, refunds, or external accounting synchronization. Credit notes are limited to an invoice's open unpaid balance. Verify tax treatment, authorization, sequence ranges, organization access and CSV compatibility with Finance before production use. Configure backups and the Laravel scheduler as part of deployment.
