# Top-level Module 11 — Administration

**Status:** Available locally, with commissioning boundaries. This guide follows the supplied top-level menu and maps it to the installed ERP administration and governance workflows.

## Menu

1. Organization
2. Departments
3. Users
4. Roles
5. Permissions
6. Approvals
7. Notifications
8. Integrations
9. Settings
10. Audit
11. Backup & Monitoring

All administration links are shown to the superadmin, who has system-wide administration access. Integrations and Backup & Monitoring remain dependent on their corresponding modules and grants. These entries use the current administrator access controls; assigning broad administration to additional roles requires an approved role matrix.

## Installed workflows

- **Organization:** browse organizations, legal entities, organization profiles and reporting lines. Organization records can be managed through the organization structure catalogue.
- **Departments:** choose an organization and open its department catalogue. Department relationships are organization-scoped and can be connected to memberships and reporting lines.
- **Users:** invite and maintain ERP accounts, assign roles and organization memberships, manage sessions and MFA recovery, and review invitations.
- **Roles:** create business roles, assign permissions, require MFA and protect built-in recovery roles.
- **Permissions:** inspect the permission catalogue and adjust role grants from the role editor. Record access scopes continue to restrict the records a permission can affect.
- **Approvals:** manage configured approval workflows, requests, stages, and time-limited delegations. The approvals queue remains available in Workspace for individual approvers.
- **Notifications:** configure notification rules, templates and preferences through the installed Notifications, Approvals & Automation area; users retain their personal notification history and delivery preferences in Workspace.
- **Integrations:** review configured providers, integration registry entries, health checks, scoped API tokens and connection logs. Provider secrets remain in the approved environment or provider profile.
- **Settings:** manage organization-wide ERP settings, branding, module availability, reference data, custom CRM fields, sequences and maintenance notices.
- **Audit:** review identity events, login activity and administrative changes.
- **Backup & Monitoring:** review queue and scheduler health, record backup outcomes, track security incidents and retention controls, and record restore-test results.

## Commissioning boundaries

The supplied decision register marks module ownership, user roles, required data, approval behavior, notifications and integration dependencies for confirmation. The current workflows provide the installed foundation but do not assume those decisions:

- Organization profiles and department structures use the existing organization model; separate multi-entity policy, location hierarchy and department-head approval rules require business decisions.
- User provisioning uses administrator invitation and assignment. Automated HR-driven provisioning and approval-before-provisioning are not enabled.
- Approval workflows are configurable in the installed automation module; each business process must be explicitly configured with its approvers, stages and SLA.
- Notification event recipients and delivery channels depend on each workflow and configured email/SMS services. No unapproved broadcast or employee celebration rules are presumed.
- Integrations expose registry and runtime connection setup but do not install or provision external systems.
- Backup records and monitoring do not create host backups. Actual backup scheduling, storage, restore and alert escalation remain responsibilities of the configured hosting and operations platform.
- Audit pages surface installed identity/admin/security records. They are not a tamper-proof external archive or a substitute for approved retention policy.

Before production rollout, the system owner should approve the administration role matrix, organization ownership model, approval policies, notification recipients/channels, integration owners, audit retention, backup cadence and recovery objectives.
