# Top-level Module 4: Commercial & Sales

This top-level module combines the sales pipeline, quotations, commercial pricing, contract lifecycle, customer onboarding, renewals, campaigns and commercial reporting. The approved submenu sequence is:

1. Commercial Dashboard
2. Opportunities
3. Pipeline
4. Targets
5. Service Catalogue
6. Price Lists
7. Quotations
8. Discounts
9. Contracts
10. Onboarding
11. Renewals
12. Campaigns
13. Reports

## Workflows

- **Dashboard:** organization and access-scoped open opportunity value, weighted forecast, pending quote approvals, active contracts, upcoming renewals, current targets and campaign count where the user is authorized.
- **Opportunities and pipeline:** create and manage opportunities, review the opportunity list, view the stage board, track activities and request/record discount approval under the existing sales workflow.
- **Targets and service catalogue:** manage sales targets, pipeline stages, service lines and packages using the established sales module.
- **Price lists:** Sales proposes dated price lists with a currency and itemized service/pricing information. Finance or another authorized reviewer approves or rejects; the requester cannot approve their own proposal. Submission and decision are audited. Approved items are selectable on quotations only while the list is effective, and the quote currency must match. A quote line retains its price-list-item reference for traceability.
- **Quotations:** versioned quotes can be submitted for independent approval. Approved quotations can progress to contracts under the existing contract workflow.
- **Discounts:** a dedicated queue surfaces pending opportunity and quote discount approvals. Existing approval rules remain in force.
- **Contracts, onboarding and renewals:** contracts retain private signed-document handling and activation requirements. Dedicated queues show contracts with open onboarding actions and active contracts with renewal dates.
- **Campaigns:** the menu opens the existing campaign planning workspace. Delivery remains subject to its configured integrations and permissions.
- **Reports:** the existing organization-scoped commercial forecast and export remain available.

## Permissions and setup

`IdentitySeeder` registers `commercial.view`, `commercial.prices.manage`, `commercial.prices.approve` and `commercial.discounts.view`. Sales roles can propose price lists; Finance can review them. Administrators retain the full permission set. After updating, apply the additive migration and reseed built-in role grants:

```bash
php artisan migrate --force
php artisan db:seed --class=IdentitySeeder --force
```

No price list, service price, tax rate, discount threshold or campaign content is fabricated by migration or seeding. The exact pricing governance and discount thresholds remain organization decisions; existing approval behavior is retained until those policies are configured.

## Current scope limits

The campaign submenu uses the existing campaign planning feature; it does not send outbound messages by itself. Price lists are currency-aware and effective-dated, but the application does not calculate taxes or infer discount limits. Renewals are shown from contract dates and existing notifications; renewal terms require user review.
