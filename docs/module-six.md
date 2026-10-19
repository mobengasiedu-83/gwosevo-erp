# Module 06 — Quotations, Contracts & Client Onboarding

**Status:** Installed. Configure approved templates and pricing, review permissions, and complete commercial and Operations acceptance before using live client agreements.

## Installed workflows

- Opportunity-linked quotations with fixed, recurring, usage-based and negotiated line pricing; currency, validity, discount and calculated totals.
- Immutable approved/rejected quote states, sequential versions, justification-based independent approval and browser Print / Save as PDF.
- Contract drafts created from approved quotations; organization-scoped templates; signed-document upload; term, renewal date, SLA and deliverables tracking.
- Onboarding checklists, assignees, client visibility, private document storage and a separate approval step.
- Commercial-to-Operations handover, with all onboarding items completed or approved before submission and a different user required to accept.
- Expiring, revocable client links for active contract information, selected onboarding items and explicitly client-visible documents.
- Daily reminders for renewals due within 30 days and creation of a linked renewal opportunity.
- Audit events for quotes, approvals, contracts, templates, onboarding, documents, handover and portal link creation.

## Commissioning and permissions

1. Apply the additive migration and seed the Module 6 permissions with the commands below. No existing commercial, identity or organization data is reset.
2. Configure approved contract templates and determine the client data and files suitable for portal sharing. Portal links are bearer credentials: distribute them through an approved channel and revoke them when access ends.
3. Review quotes, contracts, onboarding and handover permissions for each role. Sales can draft and update quotes/contracts; Finance reviews quote approvals; Operations can process onboarding and handovers. Administrator has all module permissions.
4. Agree currency, tax, discount authority, pricing units, SLA language and renewal notice policy with business owners. The specification does not define tax, quote thresholds, pricing catalog values or legal contract language; this module does not invent them.
5. Run Laravel's scheduler for daily renewal alerts. Upload executed contracts after external signing; the application does not provide electronic signatures.

```bash
php artisan migrate --force
php artisan db:seed --class=IdentitySeeder --force
```

## Operating guide

- Create a quote from an opportunity, enter one or more pricing lines and save it as a draft. Submit it with a business justification. An independent user with `quotes.approve` approves or rejects it. Create a new version for changes after a decision; previous versions remain available.
- Open an approved quote and create its contract draft. Add contract dates, SLA and deliverables, choose an approved template and upload the executed document before changing status to Active.
- Add onboarding checklist items, assign staff, upload private files and select which items/files the client may see. Mark work complete, then have another authorized user approve it where approval is required.
- Submit handover after all checklist items are complete or approved. Another user with handover permission accepts it for Operations.
- Set a renewal date to receive a dashboard notification during the 30-day window. Create a renewal opportunity from the contract page. The daily scheduler must be running.
- Create a client link for 1–90 days and share it with the client. The raw link is displayed only once; the database stores only its SHA-256 hash. Revoke active links from the contract page.

## Boundaries

PDF output uses the browser print dialog (Print / Save as PDF). Contract templates are stored and selected as drafting references; users prepare the contract using approved language and upload the executed copy. No tax calculation, e-signing, payment, invoicing, price-book approval thresholds or external client identity service is included. Public client access is restricted to active contracts, client-visible checklist items and explicitly client-visible files.
