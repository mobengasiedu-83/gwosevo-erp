# Module 03 — Organization, Departments & Branches

Installed in the existing application on 17 October 2026 against the supplied Module Feature Breakdown. Open **Organization structure** from the workspace navigation.

| Requirement | Implementation |
| --- | --- |
| Company structure | Legal entities with registration/tax/contact details, currency/time zone; operating organizations linked to a legal entity with parent/child hierarchy |
| Departments | Organization-scoped codes, descriptions, parent departments and management ownership |
| Branches | Office/operational locations, address, city/country, contact details and branch manager |
| Teams | Department ownership, supervisor and membership roster through people assignments |
| Positions | Titles, grades, job descriptions, department and position-to-position reporting relationships |
| Reporting hierarchy | Per-organization reporting managers, optional approval managers and automatic reporting-manager fallback for approval lines |
| Cost centres | Hierarchy, department, budget owner, currency, optional budget, cost/revenue/both classification and percentage allocation per membership |
| Organization chart | Expandable, accessible reporting/approval chart with active people, positions, departments and teams; full assignment roster below |

## Setup workflow

1. Create legal entities and operating organizations. Existing organizations remain usable without inventing legal-registration data; link them to legal entities when that information is available.
2. Grant organization memberships through **Users & access**. Account creation continues to use invitations; structure setup never creates or resets passwords.
3. Add departments, branches, teams, positions and cost centres inside an organization. Managers and supervisors must be active organization members.
4. Open **People & chart → Edit assignment** to set the member's department, team, branch, position, reporting manager, approval manager and cost split. Save to reveal additional allocation rows if needed.
5. Review reporting and approval charts. An omitted approval manager uses the reporting manager. These are organizational authority lines; future purchasing/finance modules implement their own approval steps.

Cost splits are optional. If supplied, distinct active cost centres must total exactly 100%, calculated in integer hundredths. Cost/revenue classification and budgets are organizational allocation metadata; they do not post accounting transactions or implement the future finance module.

## Controls

Only Administrators maintain master records and assignments. Staff can view structure only for their actual active organization memberships. Records cannot be accessed through another organization's route. Foreign assignments, position/department mismatches, incompatible team/department access scopes, and reporting/approval/parent cycles are rejected.

Codes are immutable and unique within each organization; legal-entity and organization codes are globally unique. Records are deactivated rather than deleted. Active dependants and member assignments prevent deactivation. Membership removal is blocked while management/reporting responsibilities or cost allocations depend on it. Module Two access changes also check existing position assignments, and access-review snapshots include branch, position and management relationships.

Writes use transactions and organization locks, with audit snapshots visible in System configuration → Audit. Department/team assignment changes revoke the user's sessions/tokens so the existing data-scope controls apply immediately. Existing passwords, MFA enrollment and Microsoft configuration are untouched.

## Verification

The additive migration was applied to the local MySQL database. Before/after fingerprints verified unchanged user credentials, statuses, roles, MFA data and existing memberships. No production accounts or fabricated legal entities were seeded.

Automated feature tests cover every catalogue/form, write/audit flow, staff authorization, route ownership, company constraints, hierarchy cycles, complete assignments, cost-split rollback, cross-organization references, password preservation and deactivation/removal safeguards. Desktop and 390-pixel mobile Chrome checks use an isolated synthetic SQLite database, including real login/logout, quick navigation and overflow/JavaScript checks.
