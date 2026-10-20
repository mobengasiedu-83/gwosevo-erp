# Module 07 — Contact Centre & VitalPBX

**Status:** Installed for ERP call workflows. Live PBX interoperation depends on provider-specific commissioning.

## Available workflows

- Organization-scoped call register for inbound, outbound, answered, missed and abandoned calls.
- Customer screen-pop lookup and exact normalized phone matching against CRM contacts and accounts.
- Call disposition, notes and follow-up date/owner.
- PBX connection profiles with encrypted API token and webhook secret, agent-to-extension mapping, signed inbound event endpoint, and permission-controlled VitalPBX click-to-dial.
- Queue and agent activity summary, where events provide the data.
- Permission-controlled recording references and expiry metadata; no recording file downloads.
- Configurable QA criteria and 0–5 call scoring.

## Commissioning

1. Apply the additive migration and seed the Module 7 permissions. Existing data is not reset.
2. Configure `VITALPBX_API_BASE_URL`, `VITALPBX_API_KEY`, and (when required by the API key) `VITALPBX_TENANT` in `.env`. Run `php artisan config:clear` and `php artisan vitalpbx:check` to verify the API key and HTTPS reachability. The command performs a read-only request to `/api/v2/tenants` and never prints the key.
3. Configure role grants and active organization membership for agents and supervisors.
4. Confirm the VitalPBX server exposes `/api/v2/cdr` to the ERP server, the API key can read CDRs for the intended tenant, and your call data retention policy is understood.
5. Create a PBX profile and use **Sync extensions** on the Contact Centre page to load the read-only extension directory. Map extensions to ERP users as needed. For CDR polling, the profile can use the API URL and key from `.env`; its webhook secret is only needed for signed event intake.
6. Send signed events to `/api/v1/pbx/{connection}/events`. Sign `timestamp + "." + raw_body` using HMAC-SHA256 and the webhook secret. Send the timestamp in `X-PBX-Timestamp` and the lowercase hex digest in `X-PBX-Signature`. Events older/newer than five minutes are rejected. Event names are `call.completed` and `agent.activity`; call events require an external `id` for idempotent upsert.
7. Verify mapped agents, organization matching, dashboard totals, and retention policy before operational use.

The API check verifies credentials and network/TLS reachability only. Extension sync makes a read-only `GET /api/v2/extensions` request and stores only extension number, display name, technology, and registration status. **Sync call history** imports completed CDR records from `GET /api/v2/cdr`, matches known caller numbers to CRM records, and applies mapped extensions to agents. It does not import or download recording files. CDR rows become available after calls end, so scheduled syncs overlap the last 24 hours and deduplicate records by PBX call ID. The first scheduled run imports the last seven days by default; tune `VITALPBX_CDR_INITIAL_LOOKBACK_DAYS`, `VITALPBX_CDR_OVERLAP_HOURS`, and `VITALPBX_CDR_PAGE_SIZE` if needed. The Laravel scheduler must be running on the server for automatic polling; install the standard once-per-minute `schedule:run` cron entry as described in Module 1. You can also sync manually from Contact Centre or run `php artisan vitalpbx:sync-calls`.

If the CDR endpoint reports `Call to undefined method ... getRecordingAuthParam()`, the error is inside the VitalPBX CDR API, not the ERP credentials. Update VitalPBX from the administrator menu's **Check for Updates** action, then retry. If it persists on the current release, send the exact error to VitalPBX support; do not patch the PBX vendor files manually.

The webhook accepts the documented generic event shape for installations that have an approved event adapter. VitalPBX API keys stay server-side; do not expose them to browsers.

## Permissions

`calls.view`, `calls.disposition`, `calls.configure`, `calls.dial`, `calls.recordings.view`, `calls.supervise` and `calls.qa.manage` are seeded additively. Administrator gets all Module 7 permissions; Support gets call view/disposition; Operations gets call view/disposition/supervision. Grant `calls.dial` only to staff authorized to place outbound calls. Click-to-dial uses VitalPBX `POST /api/v2/core/click_to_call`; the user's own mapped extension rings first and the destination is called after the user answers. The VitalPBX application key must have click-to-call permission for the configured tenant.

```bash
php artisan migrate --force
php artisan db:seed --class=IdentitySeeder --force
```
