# Module 10 — Marketing campaigns and lead generation

**Status:** Installed locally. The migration creates campaign, task, affiliate, landing-page, consent, and lead-attribution storage. It does not send campaign messages or create commission payments.

## Access

Open Commercial → **Marketing campaigns**. The active Administrator role is the application superadmin and receives all registered permissions, including the Module 10 permissions. Other roles need `marketing.view` and the relevant management permissions:

- `marketing.manage` — create campaigns and update status, owner, and actual spend.
- `marketing.tasks.manage` — add, complete, or reopen campaign tasks.
- `marketing.landing_pages.manage` — create draft or published landing pages.
- `marketing.affiliates.manage` — register referral partners and approved commission rates.
- `marketing.consent.manage` — record or withdraw contact preferences and view destination details.

Grant permissions through Administration → Roles & Permissions. CRM audience filters and attributed lead details also require `leads.view`; results stay within the user's existing lead access scope.

## Plan and run a campaign

1. Create a campaign with its objective, primary channel, owner, dates, optional budget, and optional CRM audience filters.
2. Open its campaign record to add tasks and assign work.
3. Change the campaign to **Active** when it is ready. Record actual spend for reporting as it accrues.
4. Create a landing page for the campaign. The generated URL is shown after saving. A page must be published and its campaign active before the public form accepts leads.
5. Review attributed leads and conversions from the campaign page. The page uses existing CRM access rules.

Audience counts are estimates based on permitted records at the time displayed. A saved segment stores its filters, not a frozen recipient list.

## Landing-page leads and consent

The public form accepts contact details, company, industry, interests, and an optional affiliate referral code. It requires an email address or phone number. New submissions are attributed to the campaign and create CRM leads.

Email and SMS consent boxes are optional and unchecked. An explicit SMS opt-in is also copied to the Module 09 SMS consent register so its send checks can honor that preference. Users with `marketing.consent.manage` can record later opt-ins or withdrawals; an SMS withdrawal synchronizes to the SMS suppression register. Enter phone numbers in international format.

Review applicable privacy notices, evidence requirements, retention periods, and marketing laws with the organization before publishing a form or contacting leads. The application records the supplied preference and source; it does not determine whether the organization's notice or legal basis is sufficient.

## Affiliate attribution and scope

Register an affiliate with a unique organization-level referral code. Add the `ref` query parameter to a landing-page URL to prefill attribution, or submit a code in the form. The campaign record shows the affiliate tied to each attributed lead. The approved commission rate is reference data only: the module does not calculate earned commission, issue invoices, or pay affiliates.

Campaign conversion counts use converted CRM leads; opportunity value, won revenue attribution, and ROI calculations are not included. Campaign budget and actual spend are planning/reporting values, not finance transactions. There are no module-specific `.env` values. Email campaign sending and SMS campaign sending are not part of Module 10; use separately configured and authorized delivery workflows.

## Local deployment

After pulling the release, run the migration and identity permission seeder using the deployment procedure. Do not copy a development `.env` over production. Module 10 uses the existing database and application key; it does not require a new environment variable.
