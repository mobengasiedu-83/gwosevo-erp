<?php

namespace App\Support;

use App\Domain\Administration\Modules;
use App\Models\User;

class WorkspaceNavigation
{
    public function groups(User $user): array
    {
        $groups = ['Workspace' => [
            ['label' => 'Workspace Overview', 'route' => 'dashboard', 'match' => 'dashboard', 'icon' => 'grid'],
        ]];
        if (app(Modules::class)->enabled('crm') && $user->can('leads.view')) {
            $groups['Commercial & CRM'] = [['label' => 'Leads', 'route' => 'leads.index', 'match' => 'leads.*', 'icon' => 'users']];
        }
        if (app(Modules::class)->enabled('sales') && $user->can('opportunities.view')) {
            $groups['Commercial & CRM'][] = ['label' => 'Sales Opportunities & Pipeline', 'route' => 'opportunities.index', 'match' => ['opportunities.index', 'opportunities.create', 'opportunities.show', 'opportunities.edit', 'opportunities.stage'], 'icon' => 'activity'];
        }
        if (app(Modules::class)->enabled('contracts') && app(Modules::class)->enabled('sales') && $user->can('quotes.view')) {
            $groups['Commercial & CRM'][] = ['label' => 'Quotations', 'route' => 'commercial.quotes.index', 'match' => 'commercial.quotes.*', 'icon' => 'file'];
        }
        if (app(Modules::class)->enabled('contracts') && $user->can('contracts.view')) {
            $groups['Commercial & CRM'][] = ['label' => 'Contracts & Client Onboarding', 'route' => 'commercial.contracts.index', 'match' => 'commercial.contracts.*', 'icon' => 'file'];
        }
        if (app(Modules::class)->enabled('sales') && $user->can('opportunities.catalog.manage')) {
            $groups['Commercial & CRM'][] = ['label' => 'Service Catalogue', 'route' => 'opportunities.catalog', 'match' => 'opportunities.catalog', 'icon' => 'grid'];
        }
        if (app(Modules::class)->enabled('sales') && $user->can('opportunities.reports.view')) {
            $groups['Commercial & CRM'][] = ['label' => 'Sales Reports', 'route' => 'opportunities.reports', 'match' => 'opportunities.reports', 'icon' => 'chart'];
        }
        if (app(Modules::class)->enabled('marketing') && $user->can('marketing.view')) {
            $groups['Commercial & CRM'][] = ['label' => 'Campaigns & marketing', 'route' => 'marketing.index', 'match' => 'marketing.*', 'icon' => 'activity'];
        }
        if (app(Modules::class)->enabled('hr') && $user->can('hr.view')) {
            $groups['Human Resources'] = [['label' => 'Employee directory', 'route' => 'hr.index', 'match' => 'hr.*', 'icon' => 'users']];
        }
        if (app(Modules::class)->enabled('recruitment') && $user->can('recruitment.view')) {
            $groups['Human Resources'][] = ['label' => 'Recruitment & Assessments', 'route' => 'recruitment.index', 'match' => 'recruitment.*', 'icon' => 'file'];
        }
        if (app(Modules::class)->enabled('performance') && $user->can('performance.view')) {
            $groups['Human Resources'][] = ['label' => 'Performance Appraisals', 'route' => 'performance.index', 'match' => 'performance.*', 'icon' => 'activity'];
        }
        if (app(Modules::class)->enabled('attendance') && ($user->can('attendance.view') || $user->can('attendance.self'))) {
            $groups['Human Resources'] ??= [];
            $groups['Human Resources'][] = ['label' => $user->can('attendance.view') ? 'Attendance & leave' : 'My attendance & leave', 'route' => 'attendance.index', 'match' => 'attendance.*', 'icon' => 'calendar'];
        }
        if (app(Modules::class)->enabled('projects') && ($user->can('projects.view') || $user->can('projects.manage') || $user->can('projects.tasks.manage'))) {
            $groups['Operations & Projects'] = [['label' => 'Projects & tasks', 'route' => 'projects.index', 'match' => 'projects.*', 'icon' => 'activity']];
        }
        if (app(Modules::class)->enabled('procurement') && ($user->isSuperAdmin() || $user->can('procurement.view') || $user->can('procurement.requests.create') || $user->can('procurement.inventory.manage') || $user->can('procurement.assets.manage'))) {
            $groups['Operations & Projects'][] = ['label' => 'Procurement', 'route' => 'procurement.index', 'match' => 'procurement.*', 'icon' => 'file'];
        }
        if (app(Modules::class)->enabled('finance') && ($user->isSuperAdmin() || $user->can('finance.view') || $user->can('finance.invoices.manage') || $user->can('finance.expenses.manage') || $user->can('finance.payments.manage') || $user->can('finance.credits.manage') || $user->can('finance.budgets.manage') || $user->can('finance.taxes.manage') || $user->can('finance.recurring.manage') || $user->can('finance.limits.manage') || $user->can('finance.reports.view'))) {
            $groups['Finance'] = [['label' => 'Finance Dashboard', 'route' => 'finance.index', 'match' => 'finance.index', 'icon' => 'grid']];
            foreach ([
                ['Invoices', 'finance.invoices.manage', 'finance.invoices.index', 'file'],
                ['Recurring Billing', 'finance.recurring.manage', 'finance.recurring.index', 'calendar'],
                ['Payments & Receipts', 'finance.payments.manage', 'finance.payments.index', 'activity'],
                ['Credit Notes', 'finance.credits.manage', 'finance.credits.index', 'file'],
                ['Expenses', 'finance.expenses.manage', 'finance.expenses.index', 'file'],
                ['Budgets', 'finance.budgets.manage', 'finance.budgets.index', 'activity'],
                ['Tax Codes', 'finance.taxes.manage', 'finance.tax-codes.index', 'file'],
                ['Approval Limits', 'finance.limits.manage', 'finance.limits.index', 'shield'],
                ['Revenue Management & Financial Reports', 'finance.reports.view', 'finance.reports', 'chart'],
            ] as [$label, $permission, $route, $icon]) {
                if ($user->isSuperAdmin() || $user->can('finance.view') || $user->can($permission)) {
                    $groups['Finance'][] = ['label' => $label, 'route' => $route, 'match' => $route, 'icon' => $icon, 'child' => true];
                }
            }
        }
        if (app(Modules::class)->enabled('contact_centre') && $user->can('calls.view')) {
            $groups['Customer Experience'] = [['label' => 'Contact Centre', 'route' => 'contact-centre.index', 'match' => 'contact-centre.*', 'icon' => 'activity']];
        }
        if (app(Modules::class)->enabled('support') && $user->can('tickets.view')) {
            $groups['Customer Experience'][] = ['label' => 'Support Tickets', 'route' => 'tickets.index', 'match' => 'tickets.*', 'icon' => 'file'];
        }
        if (app(Modules::class)->enabled('sms') && $user->can('sms.view')) {
            $groups['Customer Experience'][] = ['label' => 'SMS & Communications', 'route' => 'sms.index', 'match' => 'sms.*', 'icon' => 'file'];
        }
        if (app(Modules::class)->enabled('knowledge') && $user->can('knowledge.search')) {
            $groups['Knowledge & Intelligence'] = [['label' => 'Knowledge Bank & AI Search', 'route' => 'knowledge.index', 'match' => 'knowledge.*', 'icon' => 'search']];
        }
        if (app(Modules::class)->enabled('reports') && ($user->isSuperAdmin() || $user->canAny(['reports.dashboard.view', 'reports.custom.manage', 'reports.kpis.manage', 'reports.schedules.manage', 'leads.reports.view', 'opportunities.reports.view', 'calls.view', 'tickets.reports', 'hr.reports.view', 'attendance.reports.view', 'recruitment.reports.view', 'performance.reports.view', 'finance.reports.view', 'projects.reports.view']))) {
            $groups['Workspace'][] = ['label' => 'Reports & Insights', 'route' => 'reports.index', 'match' => 'reports.*', 'icon' => 'chart'];
        }
        if (app(Modules::class)->enabled('automation') && ($user->isSuperAdmin() || $user->canAny(['automation.view', 'automation.approve', 'automation.manage']))) {
            $groups['Notifications & Automation'] = [['label' => 'Notification Centre', 'route' => 'automation.index', 'match' => 'automation.*', 'icon' => 'activity']];
        }
        if (app(Modules::class)->enabled('integrations') && ($user->isSuperAdmin() || $user->canAny(['integrations.view', 'integrations.manage', 'integrations.tokens.manage', 'integrations.health.check']))) {
            $groups['Integrations & APIs'] = [['label' => 'Integration Registry', 'route' => 'integrations.index', 'match' => 'integrations.*', 'icon' => 'key']];
        }
        if (app(Modules::class)->enabled('security') && ($user->isSuperAdmin() || $user->canAny(['security.view', 'security.manage', 'security.incidents.manage', 'security.backups.manage', 'security.retention.manage']))) {
            $groups['Security & Compliance'] = [['label' => 'Security Centre', 'route' => 'security-center.index', 'match' => 'security-center.*', 'icon' => 'shield']];
        }
        $groups['Administration'] = [
            ['label' => 'Organization structure', 'route' => 'structure.index', 'match' => 'structure.*', 'icon' => 'users'],
            ['label' => 'Access reviews', 'route' => 'identity.reviews.index', 'match' => 'identity.reviews.*', 'icon' => 'file'],
            ['label' => 'Approval Workflows', 'route' => 'identity.delegations.index', 'match' => 'identity.delegations.*', 'icon' => 'arrow'],
        ];
        if ($user->isSuperAdmin()) {
            $groups['Administration'] = array_merge($groups['Administration'], [
                ['label' => 'System configuration', 'route' => 'admin.index', 'match' => 'admin.*', 'icon' => 'settings'],
                ['label' => 'Users & Roles', 'route' => 'identity.users.index', 'match' => 'identity.users.*', 'icon' => 'users'],
                ['label' => 'Permissions & Data Access', 'route' => 'identity.roles.index', 'match' => 'identity.roles.*', 'icon' => 'shield'],
                ['label' => 'Audit & Security', 'route' => 'identity.activity', 'match' => 'identity.activity', 'icon' => 'activity'],
                ['label' => 'Integrations & APIs', 'route' => 'identity.integrations', 'match' => 'identity.integrations', 'icon' => 'key'],
            ]);
        }
        $groups['Workspace'][] = ['label' => 'My Profile', 'route' => 'security.index', 'match' => 'security.*', 'icon' => 'shield'];

        foreach ([
            'Commercial & CRM' => 'commercial',
            'Customer Experience' => 'customer-experience',
            'Human Resources' => 'human-resources',
            'Operations & Projects' => 'operations-projects',
        ] as $group => $slug) {
            if (! empty($groups[$group])) {
                array_unshift($groups[$group], [
                    'label' => 'Overview',
                    'route' => 'group-overviews.show',
                    'parameters' => ['group' => $slug],
                    'match' => 'group-overviews.show',
                    'icon' => 'grid',
                    'overview' => true,
                ]);
            }
        }

        $order = ['Workspace', 'Commercial & CRM', 'Customer Experience', 'Human Resources', 'Operations & Projects', 'Finance', 'Notifications & Automation', 'Integrations & APIs', 'Security & Compliance', 'Knowledge & Intelligence', 'Administration'];
        $orderedGroups = [];

        foreach ($order as $group) {
            if (! empty($groups[$group])) {
                $orderedGroups[$group] = $groups[$group];
            }
        }

        return $orderedGroups;
    }
}
