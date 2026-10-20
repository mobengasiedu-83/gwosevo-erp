# Module 20 — Notifications, Approvals & Automation

Open **Notifications & Automation → Notification Centre**. The centre shows your in-app notices, current approval assignments, requests you submitted, configured workflows and delivery history for your active organization.

## Install and access

Run the migration and register permissions:

```sh
php artisan migrate --force
php artisan db:seed --class=IdentitySeeder --force
php artisan optimize:clear
```

The Administrator/superadmin receives all permissions. Grant `automation.view` to people who need the centre, `automation.approve` to approvers and `automation.manage` to workflow/template/rule administrators. Access remains restricted to the user's active organization.

## Configure a workflow

1. In **Approval workflows**, enter a workflow name and description.
2. Select one or more active organization users in each of up to eight stages; stages with no selected users are omitted. Every approver in a stage must approve before the next stage becomes active.
3. Set the stage SLA in hours and create the workflow.
4. Staff can submit requests from **Submit an approval request**. A requester cannot be listed as an approver in that workflow.
5. Approvers decide from **Approval inbox**. A rejection closes the request; approval advances to the next stage or completes the request. A valid, active time-bound approval delegate may decide for the assigned approver.

These are generic approval records. They do not change finance, leave, procurement or other module records. Continue using each module's own approval action for decisions that must update its business record.

## Notifications and delivery channels

Each user configures **In-app**, **Email** and **SMS** separately for approval requests, decisions, reminders and system alerts. In-app is enabled by default; email and SMS are off. Email uses the app's configured Laravel mail transport. SMS requires an employee work phone, active TXTConnect settings, enabled SMS module and active opt-in consent. SMS sends use the `sms` queue and are also logged in `storage/logs/sms.log`.

Workflow requests generate in-app alerts for the next approvers. Email and SMS are sent only if each recipient opted into those channels for the event. Save a template for the relevant event and channel to customize its message. Supported placeholders are `{user_name}`, `{event_title}` and `{approval_url}`.

## Reminders, rules and logs

The Laravel scheduler runs `automation:send-reminders` hourly. It reminds pending-stage approvers after the workflow SLA and repeats reminders no more than once per 24 hours. Enabled overdue rules can also notify the requester. The queue worker must be running to send queued SMS.

The delivery log records channel, recipient, status, attempt count and failure reason. Email details are also in `storage/logs/email.log`; SMS API request/response details are in `storage/logs/sms.log`. Do not expose these logs to untrusted users because they contain recipient details and provider responses.

## Production deployment

Commit and deploy the code, then run `php artisan migrate --force`, `php artisan db:seed --class=IdentitySeeder --force` and `php artisan optimize:clear`. Keep the Laravel scheduler active and run queue workers including the `sms` queue. Configure SMTP and TXTConnect in the server environment; do not copy local `.env` credentials. Check organization membership, workflow approvers, channel preferences, SMS consent and the delivery log before production use.
