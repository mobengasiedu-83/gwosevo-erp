# Module 09 — SMS and Communication Management

**Status:** Installed locally. The migration and permission registry have been applied to the local application database. TXTConnect credentials and outbound messages are not configured or sent by this change.

## Local setup

Add the approved TXTConnect values to `.env`:

```dotenv
TXTCONNECT_BASE_URL=https://api.txtconnect.net/dev/api
TXTCONNECT_API_KEY=
TXTCONNECT_SENDER=
TXTCONNECT_UNICODE=1
TXTCONNECT_TIMEOUT=15
```

Set `TXTCONNECT_UNICODE=1` to match the supplied working Postman request; use `0` for regular SMS. The selected string value is sent in the JSON body. Keep the API key out of source control. Clear cached config after changing `.env` with `php artisan config:clear`, then restart any queue worker so it loads the updated setting. The sending integration uses TXTConnect's HTTPS send endpoint with Bearer authentication. Confirm the sender ID and permitted account use with TXTConnect before enabling live sends.

## Access and consent

The permission names are `sms.view`, `sms.send`, `sms.manage`, `sms.consent.manage` and `sms.campaigns.manage`. The active Administrator role is the application superadmin and has all registered permissions. Other roles receive only their configured grants; assign SMS sending, campaign, consent and template permissions only to the staff responsible for them.

Open Operations → SMS centre. Record each recipient's explicit opt-in and evidence source before sending. The active organization and opt-in record are checked again for each message/campaign. Opt-out records block future sends. Enter phone numbers in international format. The ERP does not infer consent from CRM data or automatically import CRM contacts.

Templates are organization-specific and categorized as transactional or campaign. A single transactional message can be linked to a CRM contact or support ticket. Campaigns accept up to 5,000 entered numbers, reject the entire submission if any number lacks active opt-in, and can be sent immediately or scheduled. Scheduled dispatch runs through `sms:dispatch-scheduled` once per minute.

## Queue and reporting

Run a queue worker that includes the `sms` queue, such as `php artisan queue:work --queue=sms,default`. Keep `php artisan schedule:run` running each minute for scheduled campaigns. Check **Message history** for queued, submitted and failed records; the provider message ID and error summary are recorded. Retry a failed message manually up to three total attempts. The official send endpoint does not document provider-side idempotency, so automatic retries are disabled to reduce duplicate sends after an ambiguous network failure.

TXTConnect's documentation also lists delivery status APIs, but their request/response contract is not exposed in the currently readable provider reference. The ERP records the provider's accepted response as **Submitted**, not **Delivered**; delivery receipts and opt-out event ingestion remain to be integrated against the provider's confirmed API contract. SMS body and phone data are stored in the ERP database and should be protected under the organization's access, retention and backup policies.
