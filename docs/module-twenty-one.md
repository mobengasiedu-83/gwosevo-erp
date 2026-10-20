# Module 21 — Integration & API Management

**Edition:** 20 October 2026 · **Local status:** Installed

Open **Integrations & APIs → Integration Registry**. The page is organization scoped. Access is controlled by `integrations.view`, `integrations.manage`, `integrations.health.check`, and `integrations.tokens.manage`; the Administrator role has all four after running the additive identity seeder.

## Registry

Use the registry to record a provider, a useful label, an optional HTTPS endpoint, its owner, and where its credentials are maintained. The registry deliberately stores no provider secret and does not change the provider's live configuration. Deactivating a registry row changes its documentation status only.

Keep credentials in the existing provider configuration:

| Provider | Credential/configuration source |
| --- | --- |
| VitalPBX | Server `.env` (`VITALPBX_API_*`) or an encrypted organization PBX profile in Contact Centre |
| TXTConnect | Server `.env` (`TXTCONNECT_*`); Module 09 handles sending and SMS logs |
| Microsoft 365 / Entra | Server `.env` (`ENTRA_*`) and the existing identity/knowledge settings |
| Qdrant | Server `.env` (`KNOWLEDGE_QDRANT_*`) and Module 18 |
| USSD Manager | Separately operated service and its approved interface |

For secret rotation, issue a replacement credential with the provider, update it at that source, clear Laravel's configuration cache with `php artisan optimize:clear`, and verify the service. Revoke the previous provider credential after the replacement works. For API tokens created here, create a replacement token, update the client, then revoke the old token.

## Connectivity checks

Choose **Check status** for an explicit, read-only probe. VitalPBX reads the tenant endpoint; Microsoft checks public OpenID discovery; Qdrant reads collection metadata; TXTConnect checks endpoint reachability without sending an SMS. A successful response confirms only that endpoint responded, not that every provider operation is authorized. USSD and custom URLs are not contacted because the ERP has no approved protocol for them. Arbitrary registry URLs are never probed.

Each check stores provider, result, HTTP status, latency, operator, and correlation ID in the organization health history and `storage/logs/integrations.log`. Logs omit API keys, bearer tokens, SMS bodies and provider response bodies. A configuration-missing result means required local settings are absent or the check is intentionally unsupported.

## Scoped API tokens

An authorized user can issue and revoke their own Sanctum tokens from this page. Select only the required scopes and an expiry of 1–365 days. The plaintext token is displayed once; copy it into the consuming service's secret store. Use it as `Authorization: Bearer <token>`.

| Scope | Route |
| --- | --- |
| `profile:read` | `GET /api/user` |
| `leads:create` | `POST /api/v1/leads` |
| `tickets:create` | `POST /api/v1/tickets` |
| `tickets:reply` | `POST /api/v1/tickets/{ticket}/replies` |

API scopes are an additional limit: the account must remain active, retain the required ERP permissions, and have organization access. API endpoints also have rate limits. Existing website capture and signed PBX event endpoints keep their separate authentication methods.

## Boundaries

This module adds a registry, scoped API token management, health history, and integration logging. Provider adapters remain in their existing modules. It does not provide generic inbound/outbound webhook authoring, event delivery receipts, USSD API integration, universal credential vault/rotation, automatic retries/idempotency/dead-letter management, or a queue dashboard. Those require approved provider protocols and operational policies before implementation.

## Install and update

Locally, run:

```sh
php artisan migrate
php artisan db:seed --class=IdentitySeeder
php artisan optimize:clear
```

The migration enables Module 21 and creates the organization registry and health-history tables. The seeder adds permissions while preserving existing Administrator role grants. In production, back up the database first, deploy the code, then run the same commands as the site's PHP account; keep `.env` and provider secrets server-specific.
