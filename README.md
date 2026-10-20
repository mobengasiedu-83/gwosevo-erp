# GWO SEVO ERP

Laravel 12, Blade and Livewire ERP for GWO SEVO Solutions. Modules 01–15 are installed locally: system administration, identity and access, organization structure, CRM lead management, sales pipeline, quotations/contracts/client onboarding, contact centre with VitalPBX integration, customer support ticketing, TXTConnect SMS, marketing campaigns, human resources, recruitment/assessments, performance management, attendance/leave/self-service, and project/task productivity.

The UI follows the GWO SEVO website's green/red/yellow/charcoal brand, using local logos, imagery, Inter and Poppins. It includes responsive workspace navigation, keyboard quick navigation (Ctrl/Cmd K), branded authentication pages and accessible confirmation dialogs. It honors reduced-motion preferences.

## User documentation

Read the [complete user guide](docs/user-guide/README.md) for sign-in, navigation, Modules 01–15, administration, troubleshooting and setup. The [22-module catalogue and roadmap](docs/user-guide/module-roadmap.md) identifies installed, partial and planned capabilities. See also the [Module 09 SMS setup guide](docs/module-nine.md), [Module 10 marketing guide](docs/module-ten.md), [Module 11 HR guide](docs/module-eleven.md), [Module 12 recruitment guide](docs/module-twelve.md), [Module 13 performance guide](docs/module-thirteen.md), [Module 14 attendance guide](docs/module-fourteen.md), and [Module 15 projects guide](docs/module-fifteen.md).

## Current local installation

Open `https://localhost/gwosevo-erp/public/`. Sign in with the existing database account. Administrator credentials are not stored in `.env` or seeders.

- **Organization structure**: legal entities, operating organizations, departments, branches, teams, positions, reporting/approval charts and cost-centre allocations.
- **Overview**: scoped lead metrics, follow-ups, activity and workspace shortcuts.
- **System configuration**: settings, branding, preferences, module controls, custom fields, reference data, numbering, import/export, health and maintenance.
- **Users & access**: invitations, account status, memberships and Microsoft identity linking.
- **Roles & permissions**, **Audit activity**, **SSO & email**, **Access reviews**, **Approval delegation**, and **My security** are available from navigation.

Mandatory MFA enrollment remains disabled using `IDENTITY_MFA_REQUIRED=false`; already enrolled authenticators continue to be required. Microsoft sign-in remains disabled using `ENTRA_ENABLED=false` until the operator configures the secret and exact registered redirect URI. Existing superadmin credentials are retained in the database.

## Development and verification

```sh
composer install
php artisan migrate
npm install
npm run build
php artisan test
```

On an existing installation, seed newly introduced identity permissions with `php artisan db:seed --class=IdentitySeeder`. Seeders do not create users or reset passwords. Never run `migrate:fresh` against the existing database.

Laravel environment configuration lives in `.env`; `.env.example` contains placeholders. Build assets during development and ship `public/build` and the local brand assets; production does not need a Node process.

## Operations and scope

Run the Laravel scheduler once a minute and the configured queue worker/Horizon as host services. The health screen reports their heartbeats; their continual operation is not implied by application installation. The periodic access-review command is `php artisan identity:review-due`.

Reference import/export supports CSV and XLSX, up to 1,000 data rows and 2 MB. The first workbook worksheet is resolved through its relationships. PDF output uses the branded print template and the browser's Save as PDF option.

All three modules are installed, but external SSO acceptance, mail-delivery acceptance, continuous workers/scheduler, production backup/restore and business UAT remain deployment responsibilities. Future-module sequence definitions and permission names do not mean those business modules are installed.

See [installation review](docs/module-installation-review.md), [Module 01](docs/module-one.md) and [Module 02](docs/module-two.md) and [Module 03](docs/module-three.md) for implementation details and boundaries.
