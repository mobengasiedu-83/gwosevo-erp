# Top-level Module 3 — Social Media Management

**Status:** Installed locally for planning and manual activity capture. See the [user guide](user-guide/README.md) for navigation.

The Social Media Management menu contains the 15 specified areas:

1. Social Media Dashboard
2. Connected Accounts
3. Unified Social Inbox
4. Posts & Publishing
5. Content Calendar
6. Comments & Engagement
7. Social Leads
8. Campaign Management
9. Content Library
10. Social Listening
11. Team Assignments
12. Approval Workflows
13. Social Analytics
14. Automation Rules
15. Social Media Settings

## What is available

- Organization-scoped account profiles, inbox items, draft posts, private content assets, local assignment rules and workspace preferences.
- Internal status updates, assignments, response notes and post approval decisions.
- Social lead capture into CRM. Email/SMS marketing consent is recorded only when the operator explicitly checks the matching opt-in box.
- Campaign Management reuses the Module 10 marketing campaign register.
- Local reports summarize planned posts, captured inbox activity and social leads.

## Provider boundary

This installation does not connect to Facebook, Instagram, LinkedIn, X, TikTok or YouTube. It does not retrieve platform content, send replies, publish posts, run paid campaigns, or import reach/impression/engagement analytics. Account profiles are explicitly marked **Not connected**; approval is an internal review only. Automation is limited to assigning items entered manually.

The supplied functional decision register says platform API entitlements, publishing privileges, webhook access and permissions have not been established. Before adding a live connector, obtain the required platform access and business approval, then define token storage, scopes, webhook verification, rate limits, audit and data-retention rules. No credentials are stored by this module.

## Local setup

From the Laravel project root:

```sh
php artisan migrate --path=database/migrations/2026_10_20_300000_install_top_level_social_media_management.php
php artisan db:seed --class=IdentitySeeder
```

The migration creates organization-scoped tables for account profiles, posts, inbox items, assets, assignment rules and settings, and enables the `social_media` module. The seeder adds the `Social Media Manager` role and its scoped social permissions. CRM lead capture also requires CRM to be enabled and the user to have `leads.view` and `leads.create`.

Do not place platform passwords or API tokens in the account-profile fields. Asset uploads are kept on Laravel's private local disk and served through an organization-scoped download route.
