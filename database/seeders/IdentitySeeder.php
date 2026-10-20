<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class IdentitySeeder extends Seeder
{
    public function run(): void
    {
        $ticketPermissions = ['tickets.view', 'tickets.create', 'tickets.update', 'tickets.assign', 'tickets.reply', 'tickets.internal', 'tickets.manage', 'tickets.reports', 'tickets.portal'];
        $smsPermissions = ['sms.view', 'sms.send', 'sms.manage', 'sms.consent.manage', 'sms.campaigns.manage'];
        $marketingPermissions = ['marketing.view', 'marketing.manage', 'marketing.tasks.manage', 'marketing.consent.manage', 'marketing.landing_pages.manage', 'marketing.affiliates.manage', 'marketing.reports.view'];
        $hrPermissions = ['hr.view','hr.manage','hr.sensitive.view','hr.sensitive.manage','hr.contracts.manage','hr.documents.view','hr.documents.manage','hr.cases.view','hr.cases.manage','hr.training.manage','hr.reports.view'];
        $recruitmentPermissions = ['recruitment.view','recruitment.manage','recruitment.requisitions.approve','recruitment.applications.view','recruitment.applications.manage','recruitment.interviews.manage','recruitment.assessments.manage','recruitment.offers.manage','recruitment.offers.approve','recruitment.hire','recruitment.reports.view'];
        $performancePermissions = ['performance.view','performance.manage','performance.reviews.self','performance.reviews.manager','performance.reviews.approve','performance.calibrate','performance.kpis.manage','performance.feedback.manage','performance.feedback.respond','performance.plans.manage','performance.reports.view'];
        $attendancePermissions = ['attendance.view','attendance.manage','attendance.self','attendance.clock','attendance.approve','attendance.balances.manage','attendance.shifts.manage','attendance.reports.view','attendance.timesheets.manage'];
        $projectPermissions = ['projects.view','projects.manage','projects.tasks.manage','projects.tasks.assign','projects.approve','projects.comments','projects.attachments','projects.risks.manage','projects.time.manage','projects.reports.view'];
        $financePermissions = ['finance.view','finance.invoices.manage','finance.approve','finance.payments.manage','finance.credits.manage','finance.expenses.manage','finance.expenses.approve','finance.budgets.manage','finance.taxes.manage','finance.limits.manage','finance.reports.view','finance.export','finance.recurring.manage'];
        $procurementPermissions = ['procurement.view','procurement.suppliers.manage','procurement.requests.create','procurement.requests.approve','procurement.orders.manage','procurement.receipts.manage','procurement.inventory.manage','procurement.assets.manage','procurement.disposals.approve','procurement.reports.view'];
        $knowledgePermissions = ['knowledge.search','knowledge.spaces.manage','knowledge.members.manage','knowledge.articles.create','knowledge.articles.edit','knowledge.articles.review','knowledge.articles.publish','knowledge.articles.archive','knowledge.documents.view','knowledge.documents.upload','knowledge.ai.ask','knowledge.feedback','knowledge.analytics.view','knowledge.sync.manage'];
        $reportPermissions = ['reports.dashboard.view','reports.custom.manage','reports.schedules.manage','reports.kpis.manage'];
        $permissions = ['leads.view', 'leads.create', 'leads.update', 'leads.archive', 'leads.export', 'leads.approve', 'leads.assign', 'leads.qualify', 'leads.convert', 'leads.disqualify', 'leads.import', 'leads.reports.view', 'leads.rules.manage', 'leads.duplicate.review', 'opportunities.view', 'opportunities.create', 'opportunities.update', 'opportunities.assign', 'opportunities.approve', 'opportunities.catalog.manage', 'opportunities.targets.manage', 'opportunities.reports.view', 'quotes.view', 'quotes.create', 'quotes.update', 'quotes.approve', 'contracts.view', 'contracts.create', 'contracts.update', 'contracts.templates.manage', 'contracts.onboarding.manage', 'contracts.handover', 'contracts.portal.manage', 'calls.view', 'calls.disposition', 'calls.configure', 'calls.dial', 'calls.recordings.view', 'calls.supervise', 'calls.qa.manage', ...$ticketPermissions, ...$smsPermissions, ...$marketingPermissions, ...$hrPermissions, ...$recruitmentPermissions, ...$performancePermissions, ...$attendancePermissions, ...$projectPermissions, ...$financePermissions, ...$procurementPermissions, ...$knowledgePermissions, ...$reportPermissions];
        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        foreach ([
            'Administrator' => $permissions,
            'Sales' => ['leads.view', 'opportunities.view', 'opportunities.create', 'opportunities.update', 'opportunities.assign', 'opportunities.reports.view', 'quotes.view', 'quotes.create', 'quotes.update', 'contracts.view', 'contracts.create', 'contracts.update', 'contracts.onboarding.manage', 'contracts.handover', 'contracts.portal.manage'],
            'Finance' => ['leads.view', 'opportunities.view', 'opportunities.approve', 'opportunities.reports.view', 'quotes.view', 'quotes.approve', 'contracts.view'],
            'Procurement' => $procurementPermissions,
            'Knowledge Manager' => $knowledgePermissions,
            'Read Only' => ['leads.view', 'opportunities.view'],
            'Support' => ['calls.view', 'calls.disposition'],
            'Operations' => ['leads.view', 'calls.view', 'calls.disposition', 'calls.supervise', 'contracts.view', 'contracts.onboarding.manage', 'contracts.handover'],
        ] as $name => $grants) {
            $role = Role::findOrCreate($name, 'web');
            if ($role->wasRecentlyCreated) {
                $role->givePermissionTo($grants);
            }
        }
        // Add newly registered permissions to built-in roles without removing administrator custom grants.
        foreach ([
            'Administrator' => $permissions,
            'Knowledge Manager' => $knowledgePermissions,
            'Support' => ['tickets.view', 'tickets.create', 'tickets.update', 'tickets.assign', 'tickets.reply', 'tickets.internal', 'tickets.manage', 'tickets.reports', 'tickets.portal'],
            'Operations' => ['tickets.view', 'tickets.create', 'tickets.update', 'tickets.assign', 'tickets.reply', 'tickets.internal', 'tickets.reports', 'projects.view','projects.tasks.manage','projects.tasks.assign','projects.comments','projects.attachments','projects.time.manage','projects.reports.view'],
            'Finance' => ['finance.view','finance.invoices.manage','finance.approve','finance.payments.manage','finance.credits.manage','finance.expenses.manage','finance.expenses.approve','finance.budgets.manage','finance.taxes.manage','finance.limits.manage','finance.reports.view','finance.export','finance.recurring.manage'],
            'Procurement' => $procurementPermissions,
            'Human Resources' => [...$hrPermissions, 'recruitment.view','recruitment.manage','recruitment.applications.view','recruitment.applications.manage','recruitment.interviews.manage','recruitment.assessments.manage','recruitment.offers.manage','recruitment.hire','recruitment.reports.view','performance.view','performance.reviews.self','performance.reviews.manager','performance.kpis.manage','performance.feedback.manage','performance.feedback.respond','performance.plans.manage','performance.reports.view', ...$attendancePermissions],
        ] as $name => $grants) {
            Role::findOrCreate($name, 'web')->givePermissionTo($grants);
        }
        foreach (['Sales', 'Operations', 'Human Resources', 'Finance', 'Support', 'Read Only'] as $name) {
            $role = Role::findOrCreate($name, 'web');
            // Never overwrite roles configured by an administrator on reseed.
            if ($role->wasRecentlyCreated && ! $role->hasPermissionTo('leads.view')) {
                $role->givePermissionTo('leads.view');
            }
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
