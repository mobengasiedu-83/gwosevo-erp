# Module 08 — Customer Support & Ticketing

**Status:** Installed locally. Migration and additive role-permission seeding are required before the screens become available.

## Install and enable

```bash
php artisan migrate
php artisan db:seed --class=IdentitySeeder
```

The migration creates organization-scoped tickets, categories, SLA policies, messages, secure attachments and SLA events. It enables the `support` module without resetting application records. The identity seeder adds ticket permissions to the Administrator, Support and Operations built-in roles without replacing other role grants. It also grants `calls.dial` to the Administrator role.

Start the Laravel scheduler on the host for SLA breach tracking:

```cron
* * * * * cd /path/to/gwosevo-erp && php artisan schedule:run >> /dev/null 2>&1
```

The scheduler runs `tickets:check-sla` every five minutes. Breaches are recorded once per ticket and response/resolution type; overdue tickets are assigned to the configured escalation owner where one is set.

## Staff workflow

1. Open **Operations → Support tickets**.
2. Add organization-specific categories and response/resolution targets for each priority under **Ticket setup**. Targets use elapsed minutes and apply when a ticket is created.
3. Choose **New ticket**. Enter requester details, request description, priority and category. Optionally link a CRM account/contact or recent Contact Centre call, attach up to five files and assign a team/agent.
4. Open a ticket to add customer-visible replies or internal notes, download authorized attachments, change ownership and move the ticket through open, assigned, in progress, waiting, resolved and closed.
5. For tickets created by staff or through the API, the ERP emails the requester the initial public message and a private portal link when a requester email is recorded. Public staff replies and status changes also trigger an email with the portal link. Internal notes and attachments are never emailed. Treat the portal link as a bearer credential.

`tickets.view`, `tickets.create`, `tickets.update`, `tickets.assign`, `tickets.reply`, `tickets.internal`, `tickets.manage`, `tickets.reports` and `tickets.portal` control the workflows. Access is scoped to the signed-in user's active organization. The support role is granted the full set; adjust that role if the duty assignment differs.

## Customer and external intake

- Public form: `/support/submit/{organization-code}`. It creates a normal-priority ticket without exposing CRM records or assignment controls. The generated private portal URL allows its holder to view public messages, reply, download public attachments and submit one satisfaction score (1–5) after resolution. Internal notes are never shown through the portal. A customer reply to a resolved/closed ticket reopens it.
- API: authenticated `POST /api/v1/tickets` accepts an active Sanctum user with `tickets.create`; the request uses that user's active organization. Use `channel=email` for a trusted inbound-mail parser or `channel=api` for another source. Existing ticket replies can be posted to `POST /api/v1/tickets/{ticket}/replies`; email replies are public customer messages and reopen a resolved/closed ticket. The ERP does not poll mailboxes itself; configure an approved mailbox processor to parse and forward messages with a Sanctum token. Callers without `tickets.assign` cannot set team or agent ownership. Outbound notifications use the configured Laravel mail transport and are addressed only to the ticket's requester email.
- Call intake: staff can link a ticket to an existing same-organization call record. No duplicate call record is created.

## SLA and attachments

Default elapsed-time targets when a priority has no explicit policy are critical (15-minute response / 4-hour resolution), high (1-hour / 24-hour), normal (4-hour / 72-hour), and low (8-hour / 7-day). Configure organization-specific values before operational use. The current SLA clock does not pause outside business hours, on weekends, while waiting for a customer, or during holidays.

Up to five attachments of 10 MB each are accepted per message. Supported types are PDF, PNG/JPEG/GIF, TXT/CSV, DOC/DOCX and XLS/XLSX. Files use Laravel's private `local` disk and authorization-checked download routes; they are not published under `public/storage`. Production operators should configure encrypted private storage, malware scanning and backups according to their policy.

## Module 07 note

Module 07 includes the VitalPBX click-to-dial API action and call-to-ticket linking. The dial action rings the current staff member's mapped extension before the destination. CDR import still depends on the PBX's CDR endpoint; the reported missing `getRecordingAuthParam()` method is an exception from the VitalPBX server and cannot be corrected by this ERP migration.
