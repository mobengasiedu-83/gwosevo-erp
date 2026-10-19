# Module 07 — Contact Centre & VitalPBX

**Status:** Installed for ERP call workflows. Live PBX interoperation depends on provider-specific commissioning.

## Available workflows

- Organization-scoped call register for inbound, outbound, answered, missed and abandoned calls.
- Customer screen-pop lookup and exact normalized phone matching against CRM contacts and accounts.
- Call disposition, notes and follow-up date/owner.
- PBX connection profiles with encrypted API token and webhook secret, agent-to-extension mapping and signed inbound event endpoint.
- Queue and agent activity summary, where events provide the data.
- Permission-controlled recording references and expiry metadata; no recording file downloads.
- Configurable QA criteria and 0–5 call scoring.

## Commissioning

1. Apply the additive migration and seed the Module 7 permissions. Existing data is not reset.
2. Configure role grants and active organization membership for agents and supervisors.
3. Confirm the approved VitalPBX/Asterisk API, AMI/CDR/CEL feed, event field mapping and recording retention policy with the telephony owner.
4. Create a PBX profile. Save its one-time webhook secret in the approved provider configuration, enter the approved HTTPS base URL, map extensions, and activate signed event intake.
5. Send signed events to `/api/v1/pbx/{connection}/events`. Sign `timestamp + "." + raw_body` using HMAC-SHA256 and the webhook secret. Send the timestamp in `X-PBX-Timestamp` and the lowercase hex digest in `X-PBX-Signature`. Events older/newer than five minutes are rejected. Event names are `call.completed` and `agent.activity`; call events require an external `id` for idempotent upsert.
6. Verify mapped agents, organization matching, dashboard totals, and retention policy before operational use.

The app does not connect to a PBX at install time. It does not expose AMI, initiate click-to-call, test the connection, fetch recordings, or purge expired recordings. Those workflows need the approved provider API contract and recording policy, which the supplied specification identifies as outstanding. The webhook accepts the documented generic event shape; map the provider-specific format through a controlled adapter rather than exposing PBX credentials to browsers.

## Permissions

`calls.view`, `calls.disposition`, `calls.configure`, `calls.dial`, `calls.recordings.view`, `calls.supervise` and `calls.qa.manage` are seeded additively. Administrator gets all Module 7 permissions; Support gets call view/disposition; Operations gets call view/disposition/supervision. `calls.dial` is reserved until a provider adapter is approved.

```bash
php artisan migrate --force
php artisan db:seed --class=IdentitySeeder --force
```
