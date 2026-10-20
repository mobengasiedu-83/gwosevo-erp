# Module 22 — Security, Audit, Backup & Monitoring

**Edition:** 20 October 2026 · **Local status:** Installed

Open **Security & Compliance → Security Centre**. The active Administrator role can access every action. Other access is assigned through **Administration → Permissions & Data Access**:

| Permission | Capability |
| --- | --- |
| `security.view` | View scoped health, audit, incident, backup, classification, rotation and retention information |
| `security.manage` | Update data classifications and track secret rotation schedules |
| `security.incidents.manage` | Record, assign and update incidents |
| `security.backups.manage` | Register backup records and restore drill results |
| `security.retention.manage` | Configure retention windows and enable eligible database categories |

## System health and events

The dashboard checks the database connection, writable application storage, application key presence (never its value), queued and failed jobs, queue and scheduler heartbeats, and whether debug mode is enabled. A healthy check is a point-in-time indication; it does not prove workers remain online. Use Administration → System configuration → Health for the existing detailed checks.

Audit history displays recent organization-scoped critical changes within the viewer's access. Administrators also see recent authentication successes and failures, including account identifiers and source IP addresses. Treat those details as restricted operational information. Existing identity and module audit events remain in their current audit log.

## Incidents and classification

Record an incident with a severity, details and optional owner; update it through open, acknowledged, investigating, resolved or closed. Add resolution notes when resolving. Incident actions are recorded in the audit history.

Classification labels describe handling expectations for public, internal, confidential and restricted data. They do not replace the existing module permissions, encryption, organization scoping or private-file access checks. Review labels and handling notes with the data owner.

## Backups and restore drills

Create database, file and full backups in the approved hosting/storage service, then register the type, time, location label and optional checksum/size. Keep the live backup files, credentials, and exact private paths in the protected infrastructure platform; the ERP stores only a location label and evidence metadata.

Run restore drills in a separate environment using the normal operator procedure, then record pass/fail and notes. This page does not create a backup, move a file, or restore data. Preserve the Laravel `APP_KEY` securely with encrypted files and database backups. Define and periodically review the business recovery point and recovery time objectives with the system owner.

## Retention

Retention policies are disabled by default. Choose a category and retention period only after the business owner has approved the required retention. Supported database categories are audit events, authentication events, integration health history and failed queue jobs.

`php artisan security:prune-retained-data` previews the enabled policies and eligible row counts. `php artisan security:prune-retained-data --apply` shows the preview and asks for confirmation before permanently deleting records. Use `--force` only from a controlled deployment/operations procedure that already performed the preview. Every applied category is recorded in the retention-run table and application log. File logs such as `email.log`, `sms.log` and `laravel.log` need host log rotation; the command does not delete them. Retention is manual, not scheduled.

## Secret rotation and compliance

Track a secret's system/name, owner and rotation dates only; never enter secret values. Rotate the credential at its provider or secret manager, update the existing `.env`/module profile, clear cached configuration where needed, verify the provider, and revoke the old credential. The rotation list is a reminder register; it does not change provider credentials.

Use the existing access-review workflow for permission recertification. Schedule periodic reviews of privileged roles, direct grants, incidents, backup records and restore drills. Approve privacy, retention and incident-response procedures with the appropriate business and technical owners before production use.

## Install and deploy

Locally, run:

```sh
php artisan migrate
php artisan db:seed --class=IdentitySeeder
php artisan optimize:clear
```

The migration enables Module 22, creates its records and inserts default classification and disabled retention policies. The additive identity seeder registers permissions and ensures the Administrator role has them. In production, back up the database before deployment and run commands as the website's PHP account. Do not run `migrate:fresh` against an existing installation.
