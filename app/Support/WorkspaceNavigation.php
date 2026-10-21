<?php

namespace App\Support;

use App\Domain\Administration\Modules;
use App\Models\User;

class WorkspaceNavigation
{
    public function groups(User $user): array
    {
        $groups = ['Workspace' => [
            ['label' => 'My Dashboard', 'route' => 'dashboard', 'match' => 'dashboard', 'icon' => 'grid'],
        ]];
        $groups['Administration'] = [];
        if (app(Modules::class)->enabled('crm') && $user->can('leads.view')) {
            $groups['CRM & Customer Management'] = [
                ['label' => 'CRM Dashboard', 'route' => 'crm.dashboard', 'match' => 'crm.dashboard', 'icon' => 'grid'],
                ['label' => 'Leads', 'route' => 'leads.index', 'match' => 'leads.*', 'icon' => 'users'],
                ['label' => 'Accounts', 'route' => 'crm.accounts', 'match' => 'crm.accounts*', 'icon' => 'users'],
                ['label' => 'Contacts', 'route' => 'crm.contacts', 'match' => 'crm.contacts*', 'icon' => 'users'],
                ['label' => 'Lead Sources', 'route' => 'crm.sources', 'match' => 'crm.sources*', 'icon' => 'file'],
                ['label' => 'Qualification', 'route' => 'crm.qualification', 'match' => 'crm.qualification', 'icon' => 'check'],
                ['label' => 'Interactions', 'route' => 'crm.interactions', 'match' => 'crm.interactions', 'icon' => 'activity'],
                ['label' => 'Follow-ups', 'route' => 'crm.followups', 'match' => 'crm.followups', 'icon' => 'calendar'],
                ['label' => 'Segmentation', 'route' => 'crm.segments', 'match' => 'crm.segments*', 'icon' => 'search'],
                ['label' => 'Conversion', 'route' => 'crm.conversion', 'match' => 'crm.conversion', 'icon' => 'arrow'],
            ];
        }
        if (app(Modules::class)->enabled('crm') && $user->can('leads.reports.view')) {
            $groups['CRM & Customer Management'][] = ['label' => 'CRM Reports', 'route' => 'leads.reports', 'match' => 'leads.reports', 'icon' => 'chart', 'child' => true];
        }
        if (app(Modules::class)->enabled('commercial') && $user->can('commercial.view')) {
            $groups['Commercial & Sales'][] = ['label' => 'Commercial Dashboard', 'route' => 'commercial.dashboard', 'match' => 'commercial.dashboard', 'icon' => 'grid'];
        }
        if (app(Modules::class)->enabled('sales') && $user->can('opportunities.view')) {
            $groups['Commercial & Sales'][] = ['label' => 'Opportunities', 'route' => 'opportunities.index', 'match' => ['opportunities.index', 'opportunities.create', 'opportunities.show', 'opportunities.edit', 'opportunities.stage'], 'icon' => 'activity'];
            $groups['Commercial & Sales'][] = ['label' => 'Pipeline', 'route' => 'opportunities.pipeline', 'match' => 'opportunities.pipeline', 'icon' => 'chart'];
        }
        if (app(Modules::class)->enabled('sales') && $user->can('manageTargets', \App\Models\Opportunity::class)) {
            $groups['Commercial & Sales'][] = ['label' => 'Targets', 'route' => 'opportunities.targets', 'match' => 'opportunities.targets*', 'icon' => 'chart'];
        }
        if (app(Modules::class)->enabled('contracts') && app(Modules::class)->enabled('sales') && $user->can('quotes.view')) {
            $groups['Commercial & Sales'][] = ['label' => 'Quotations', 'route' => 'commercial.quotes.index', 'match' => 'commercial.quotes.*', 'icon' => 'file'];
        }
        if (app(Modules::class)->enabled('contracts') && $user->can('contracts.view')) {
            $groups['Commercial & Sales'][] = ['label' => 'Contracts', 'route' => 'commercial.contracts.index', 'match' => 'commercial.contracts.index', 'icon' => 'file'];
            $groups['Commercial & Sales'][] = ['label' => 'Onboarding', 'route' => 'commercial.onboarding', 'match' => 'commercial.onboarding', 'icon' => 'check'];
            $groups['Commercial & Sales'][] = ['label' => 'Renewals', 'route' => 'commercial.renewals', 'match' => 'commercial.renewals', 'icon' => 'calendar'];
        }
        if (app(Modules::class)->enabled('sales') && $user->can('opportunities.catalog.manage')) {
            $groups['Commercial & Sales'][] = ['label' => 'Service Catalogue', 'route' => 'opportunities.catalog', 'match' => 'opportunities.catalog', 'icon' => 'grid'];
        }
        if (app(Modules::class)->enabled('commercial') && ($user->can('commercial.prices.manage') || $user->can('commercial.prices.approve'))) {
            $groups['Commercial & Sales'][] = ['label' => 'Price Lists', 'route' => 'commercial.price-lists', 'match' => 'commercial.price-lists*', 'icon' => 'file'];
        }
        if (app(Modules::class)->enabled('commercial') && $user->can('commercial.discounts.view')) {
            $groups['Commercial & Sales'][] = ['label' => 'Discounts', 'route' => 'commercial.discounts', 'match' => 'commercial.discounts', 'icon' => 'check'];
        }
        if (app(Modules::class)->enabled('marketing') && $user->can('marketing.view')) {
            $groups['Commercial & Sales'][] = ['label' => 'Campaigns', 'route' => 'commercial.campaigns', 'match' => 'commercial.campaigns', 'icon' => 'chart'];
        }
        if (app(Modules::class)->enabled('sales') && $user->can('opportunities.reports.view')) {
            $groups['Commercial & Sales'][] = ['label' => 'Commercial Reports', 'route' => 'opportunities.reports', 'match' => 'opportunities.reports', 'icon' => 'chart'];
        }
        if (app(Modules::class)->enabled('social_media') && $user->can('social.view')) {
            $groups['Social Media Management'] = [
                ['label' => 'Social Media Dashboard', 'route' => 'social.dashboard', 'match' => 'social.dashboard', 'icon' => 'grid'],
                ['label' => 'Connected Accounts', 'route' => 'social.accounts', 'match' => 'social.accounts', 'icon' => 'users'],
                ['label' => 'Unified Social Inbox', 'route' => 'social.inbox', 'match' => 'social.inbox', 'icon' => 'bell'],
                ['label' => 'Posts & Publishing', 'route' => 'social.posts', 'match' => 'social.posts', 'icon' => 'file'],
                ['label' => 'Content Calendar', 'route' => 'social.calendar', 'match' => 'social.calendar', 'icon' => 'calendar'],
                ['label' => 'Comments & Engagement', 'route' => 'social.engagement', 'match' => 'social.engagement', 'icon' => 'activity'],
                ['label' => 'Social Leads', 'route' => 'social.leads', 'match' => 'social.leads', 'icon' => 'users'],
                ['label' => 'Campaign Management', 'route' => 'social.campaigns', 'match' => 'social.campaigns', 'icon' => 'chart'],
                ['label' => 'Content Library', 'route' => 'social.assets', 'match' => 'social.assets', 'icon' => 'file'],
                ['label' => 'Social Listening', 'route' => 'social.listening', 'match' => 'social.listening', 'icon' => 'search'],
                ['label' => 'Team Assignments', 'route' => 'social.assignments', 'match' => 'social.assignments', 'icon' => 'users'],
                ['label' => 'Approval Workflows', 'route' => 'social.approvals', 'match' => 'social.approvals', 'icon' => 'check'],
                ['label' => 'Social Analytics', 'route' => 'social.analytics', 'match' => 'social.analytics', 'icon' => 'chart'],
                ['label' => 'Automation Rules', 'route' => 'social.automation', 'match' => 'social.automation', 'icon' => 'settings'],
                ['label' => 'Social Media Settings', 'route' => 'social.settings', 'match' => 'social.settings', 'icon' => 'settings'],
            ];
        }
        if (app(Modules::class)->enabled('hr') && $user->can('hr.view')) {
            $groups['Human Resources'] = [
                ['label' => 'Employees', 'route' => 'hr.index', 'match' => 'hr.index', 'icon' => 'users'],
            ];
        }
        if (app(Modules::class)->enabled('recruitment') && $user->can('recruitment.view')) {
            $groups['Human Resources'] ??= [];
            $groups['Human Resources'][] = ['label' => 'Recruitment', 'route' => 'recruitment.index', 'match' => 'recruitment.index', 'icon' => 'file'];
            $groups['Human Resources'][] = ['label' => 'Assessments', 'route' => 'recruitment.assessments', 'match' => 'recruitment.assessments', 'icon' => 'check'];
        }
        if (app(Modules::class)->enabled('hr') && $user->can('hr.view')) {
            $groups['Human Resources'][] = ['label' => 'Onboarding', 'route' => 'hr.onboarding', 'match' => 'hr.onboarding', 'icon' => 'check'];
        }
        if (app(Modules::class)->enabled('attendance') && ($user->can('attendance.view') || $user->can('attendance.self'))) {
            $groups['Human Resources'] ??= [];
            if ($user->can('attendance.view')) $groups['Human Resources'][] = ['label' => 'Attendance', 'route' => 'attendance.index', 'match' => 'attendance.index', 'icon' => 'calendar'];
            if ($user->can('attendance.view') || $user->can('attendance.self')) $groups['Human Resources'][] = ['label' => 'Leave', 'route' => 'attendance.leave', 'match' => 'attendance.leave', 'icon' => 'calendar'];
        }
        if (app(Modules::class)->enabled('performance') && $user->can('performance.view')) {
            $groups['Human Resources'] ??= [];
            $groups['Human Resources'][] = ['label' => 'Appraisals', 'route' => 'performance.index', 'match' => 'performance.index', 'icon' => 'activity'];
        }
        if (app(Modules::class)->enabled('hr') && ($user->can('hr.view') || $user->can('hr.training.manage'))) {
            $groups['Human Resources'] ??= [];
            $groups['Human Resources'][] = ['label' => 'Training', 'route' => 'hr.training', 'match' => 'hr.training', 'icon' => 'activity'];
        }
        if (app(Modules::class)->enabled('attendance') && $user->can('attendance.self')) {
            $groups['Human Resources'] ??= [];
            $groups['Human Resources'][] = ['label' => 'Self-Service', 'route' => 'attendance.self-service', 'match' => 'attendance.self-service', 'icon' => 'users'];
        }
        if (app(Modules::class)->enabled('hr') && $user->can('hr.reports.view')) {
            $groups['Human Resources'] ??= [];
            $groups['Human Resources'][] = ['label' => 'HR Reports', 'route' => 'hr.reports', 'match' => 'hr.reports', 'icon' => 'chart'];
        }
        if (app(Modules::class)->enabled('projects') && ($user->canAny(['projects.view', 'projects.manage', 'projects.tasks.manage', 'projects.time.manage', 'projects.risks.manage', 'projects.approve', 'projects.reports.view']) || $user->isSuperAdmin())) {
            $groups['Operations & Projects'] = [];
            if ($user->canAny(['projects.view', 'projects.manage', 'projects.tasks.manage']) || $user->isSuperAdmin()) {
                $groups['Operations & Projects'][] = ['label' => 'Projects', 'route' => 'projects.index', 'match' => 'projects.index', 'icon' => 'grid'];
                $groups['Operations & Projects'][] = ['label' => 'Tasks', 'route' => 'projects.workspace', 'parameters' => ['section' => 'tasks'], 'match' => 'projects.workspace', 'icon' => 'activity'];
            }
            if ($user->canAny(['projects.view', 'projects.manage', 'projects.approve']) || $user->isSuperAdmin()) {
                $groups['Operations & Projects'][] = ['label' => 'Milestones', 'route' => 'projects.workspace', 'parameters' => ['section' => 'milestones'], 'match' => 'projects.workspace', 'icon' => 'calendar'];
            }
            if ($user->canAny(['projects.view', 'projects.manage', 'projects.time.manage']) || $user->isSuperAdmin()) {
                $groups['Operations & Projects'][] = ['label' => 'Timesheets', 'route' => 'projects.workspace', 'parameters' => ['section' => 'timesheets'], 'match' => 'projects.workspace', 'icon' => 'clock'];
            }
            if ($user->canAny(['projects.view', 'projects.manage', 'projects.risks.manage']) || $user->isSuperAdmin()) {
                $groups['Operations & Projects'][] = ['label' => 'Risks', 'route' => 'projects.workspace', 'parameters' => ['section' => 'risks'], 'match' => 'projects.workspace', 'icon' => 'shield'];
            }
            if ($user->canAny(['projects.view', 'projects.manage', 'projects.approve']) || $user->isSuperAdmin()) {
                $groups['Operations & Projects'][] = ['label' => 'Deliverables', 'route' => 'projects.workspace', 'parameters' => ['section' => 'deliverables'], 'match' => 'projects.workspace', 'icon' => 'check'];
            }
            if ($user->can('projects.reports.view') || $user->isSuperAdmin()) {
                $groups['Operations & Projects'][] = ['label' => 'Operations Reports', 'route' => 'projects.workspace', 'parameters' => ['section' => 'reports'], 'match' => 'projects.workspace', 'icon' => 'chart'];
            }
        }
        if (app(Modules::class)->enabled('procurement') && ($user->isSuperAdmin() || $user->canAny(['procurement.view', 'procurement.suppliers.manage', 'procurement.requests.create', 'procurement.requests.approve', 'procurement.orders.manage', 'procurement.receipts.manage', 'procurement.inventory.manage', 'procurement.assets.manage', 'procurement.disposals.approve', 'procurement.reports.view']))) {
            $groups['Procurement & Assets'] = [];
            foreach ([
                ['Procurement Dashboard', 'procurement.view', 'dashboard', 'grid'],
                ['Suppliers', 'procurement.suppliers.manage', 'suppliers', 'users'],
                ['Requisitions', 'procurement.requests.create', 'requisitions', 'file'],
                ['Approvals', 'procurement.requests.approve', 'approvals', 'check'],
                ['RFQs', 'procurement.orders.manage', 'rfqs', 'file'],
                ['Comparisons', 'procurement.orders.manage', 'comparisons', 'chart'],
                ['Purchase Orders', 'procurement.orders.manage', 'purchase-orders', 'file'],
                ['Goods Receipt', 'procurement.receipts.manage', 'goods-receipt', 'check'],
                ['Supplier Invoices', 'procurement.orders.manage', 'supplier-invoices', 'file'],
                ['Inventory', 'procurement.inventory.manage', 'inventory', 'grid'],
                ['Asset Register', 'procurement.assets.manage', 'asset-register', 'file'],
                ['Assignments', 'procurement.assets.manage', 'assignments', 'users'],
                ['Maintenance', 'procurement.assets.manage', 'maintenance', 'settings'],
                ['Transfers & Disposal', 'procurement.assets.manage', 'transfers-disposal', 'arrow'],
                ['Reports', 'procurement.reports.view', 'reports', 'chart'],
            ] as [$label, $permission, $section, $icon]) {
                if ($user->isSuperAdmin() || $user->can('procurement.view') || $user->can($permission)) {
                    $groups['Procurement & Assets'][] = $section === 'dashboard'
                        ? ['label' => $label, 'route' => 'procurement.index', 'match' => 'procurement.index', 'icon' => $icon]
                        : ['label' => $label, 'route' => 'procurement.workspace', 'parameters' => ['section' => $section], 'match' => 'procurement.workspace', 'icon' => $icon, 'child' => true];
                }
            }
        }
        if (app(Modules::class)->enabled('finance') && ($user->isSuperAdmin() || $user->canAny(['finance.view', 'finance.invoices.manage', 'finance.expenses.manage', 'finance.payments.manage', 'finance.credits.manage', 'finance.budgets.manage', 'finance.taxes.manage', 'finance.recurring.manage', 'finance.limits.manage', 'finance.reports.view']))) {
            $groups['Finance'] = [['label' => 'Dashboard', 'route' => 'finance.index', 'match' => 'finance.index', 'icon' => 'grid']];
            foreach ([
                ['Invoices', 'finance.invoices.manage', 'finance.invoices.index', 'file'],
                ['Recurring Billing', 'finance.recurring.manage', 'finance.recurring.index', 'calendar'],
                ['Payments', 'finance.payments.manage', 'finance.payments.index', 'activity'],
                ['Credit Notes', 'finance.credits.manage', 'finance.credits.index', 'file'],
                ['Expenses', 'finance.expenses.manage', 'finance.expenses.index', 'file'],
                ['Budgets', 'finance.budgets.manage', 'finance.budgets.index', 'activity'],
                ['Revenue', 'finance.reports.view', 'finance.revenue', 'chart'],
                ['Receivables', 'finance.reports.view', 'finance.receivables', 'activity'],
                ['Reports', 'finance.reports.view', 'finance.reports', 'file'],
            ] as [$label, $permission, $route, $icon]) {
                if ($user->isSuperAdmin() || $user->can('finance.view') || $user->can($permission)) {
                    $groups['Finance'][] = ['label' => $label, 'route' => $route, 'match' => $route, 'icon' => $icon, 'child' => true];
                }
            }
        }
        if (app(Modules::class)->enabled('contact_centre') && $user->can('calls.view')) {
            $groups['Customer Experience'] = [['label' => 'Contact Centre', 'route' => 'contact-centre.index', 'match' => 'contact-centre.index', 'icon' => 'activity']];
            if ($user->can('calls.supervise')) $groups['Customer Experience'][] = ['label' => 'Live Monitoring', 'route' => 'customer-experience.live-monitoring', 'match' => 'customer-experience.live-monitoring', 'icon' => 'activity'];
            $groups['Customer Experience'][] = ['label' => 'Call Records', 'route' => 'customer-experience.call-records', 'match' => 'customer-experience.call-records', 'icon' => 'file'];
            if ($user->can('calls.recordings.view')) $groups['Customer Experience'][] = ['label' => 'Recordings', 'route' => 'customer-experience.recordings', 'match' => 'customer-experience.recordings', 'icon' => 'file'];
            if ($user->can('calls.qa.manage')) $groups['Customer Experience'][] = ['label' => 'Quality Assurance', 'route' => 'customer-experience.quality-assurance', 'match' => 'customer-experience.quality-assurance', 'icon' => 'check'];
        }
        if (app(Modules::class)->enabled('support') && $user->can('tickets.view')) {
            $groups['Customer Experience'][] = ['label' => 'Tickets', 'route' => 'tickets.index', 'match' => 'tickets.*', 'icon' => 'file'];
            $groups['Customer Experience'][] = ['label' => 'SLA', 'route' => 'customer-experience.sla', 'match' => 'customer-experience.sla', 'icon' => 'clock'];
        }
        if (app(Modules::class)->enabled('sms') && $user->can('sms.view')) {
            $groups['Customer Experience'][] = ['label' => 'SMS', 'route' => 'sms.index', 'match' => 'sms.*', 'icon' => 'file'];
        }
        if (app(Modules::class)->enabled('support') && $user->can('tickets.view')) {
            $groups['Customer Experience'][] = ['label' => 'Feedback', 'route' => 'customer-experience.feedback', 'match' => 'customer-experience.feedback', 'icon' => 'activity'];
        }
        if ((app(Modules::class)->enabled('contact_centre') && $user->can('calls.view')) || (app(Modules::class)->enabled('support') && $user->can('tickets.reports')) || (app(Modules::class)->enabled('sms') && $user->can('sms.view'))) {
            $groups['Customer Experience'][] = ['label' => 'CX Reports', 'route' => 'customer-experience.reports', 'match' => 'customer-experience.reports', 'icon' => 'chart'];
        }
        $canViewIntelligenceReports = app(Modules::class)->enabled('reports') && ($user->isSuperAdmin() || $user->canAny(['reports.dashboard.view', 'reports.custom.manage', 'reports.kpis.manage', 'reports.schedules.manage', 'leads.reports.view', 'opportunities.reports.view', 'calls.view', 'tickets.reports', 'hr.reports.view', 'attendance.reports.view', 'recruitment.reports.view', 'performance.reports.view', 'finance.reports.view', 'projects.reports.view']));
        if ((app(Modules::class)->enabled('knowledge') && $user->can('knowledge.search')) || $canViewIntelligenceReports) {
            $groups['Knowledge & Intelligence'] = [];
            if (app(Modules::class)->enabled('knowledge') && $user->can('knowledge.search')) {
                $knowledgeLinks = [
                    ['Knowledge Bank', 'knowledge.index', [], 'search'],
                ];
                if ($user->can('knowledge.documents.view')) $knowledgeLinks[] = ['Documents', 'knowledge.sections', ['section' => 'documents'], 'file'];
                $knowledgeLinks[] = ['AI Search', 'knowledge.sections', ['section' => 'ai-search'], 'search'];
                if ($user->can('knowledge.sync.manage')) $knowledgeLinks[] = ['Sources', 'knowledge.sections', ['section' => 'sources'], 'key'];
                foreach ($knowledgeLinks as [$label, $route, $parameters, $icon]) {
                    $groups['Knowledge & Intelligence'][] = ['label' => $label, 'route' => $route, 'parameters' => $parameters, 'match' => $route, 'icon' => $icon];
                }
            }
            if ($canViewIntelligenceReports) {
                foreach ([
                    ['Executive Dashboards', 'executive', 'chart'],
                    ['Custom Reports', 'custom', 'file'],
                    ['Scheduled Reports', 'scheduled', 'calendar'],
                    ['Exports', 'exports', 'arrow'],
                ] as [$label, $section, $icon]) {
                    $groups['Knowledge & Intelligence'][] = ['label' => $label, 'route' => 'reports.sections', 'parameters' => ['section' => $section], 'match' => 'reports.sections', 'icon' => $icon];
                }
            }
        }
        $groups['Workspace'][] = ['label' => 'My Tasks', 'route' => 'workspace.tasks.index', 'match' => 'workspace.tasks.*', 'icon' => 'activity'];
        $groups['Workspace'][] = ['label' => 'My Approvals', 'route' => 'workspace.approvals.index', 'match' => 'workspace.approvals.*', 'icon' => 'check'];
        $groups['Workspace'][] = ['label' => 'My Calendar', 'route' => 'workspace.calendar.index', 'match' => 'workspace.calendar.*', 'icon' => 'calendar'];
        $groups['Workspace'][] = ['label' => 'Notifications', 'route' => 'workspace.notifications.index', 'match' => 'workspace.notifications.*', 'icon' => 'bell'];
        if ($user->isSuperAdmin()) {
            $groups['Administration'] = [
                ['label' => 'Organization', 'route' => 'structure.index', 'parameters' => ['section' => 'organization'], 'match' => 'structure.index', 'icon' => 'users'],
                ['label' => 'Departments', 'route' => 'structure.index', 'parameters' => ['section' => 'departments'], 'match' => 'structure.index', 'icon' => 'grid'],
                ['label' => 'Users', 'route' => 'identity.users.index', 'match' => 'identity.users.*', 'icon' => 'users'],
                ['label' => 'Roles', 'route' => 'identity.roles.index', 'parameters' => ['section' => 'roles'], 'match' => 'identity.roles.*', 'icon' => 'shield'],
                ['label' => 'Permissions', 'route' => 'identity.roles.index', 'parameters' => ['section' => 'permissions'], 'match' => 'identity.roles.*', 'icon' => 'key'],
                ...((app(Modules::class)->enabled('automation') && $user->canAny(['automation.view', 'automation.approve', 'automation.manage'])) ? [['label' => 'Approvals', 'route' => 'automation.index', 'match' => 'automation.*', 'icon' => 'check']] : [['label' => 'Approvals', 'route' => 'workspace.approvals.index', 'match' => 'workspace.approvals.*', 'icon' => 'check']]),
                ...((app(Modules::class)->enabled('automation') && $user->canAny(['automation.view', 'automation.approve', 'automation.manage'])) ? [['label' => 'Notifications', 'route' => 'automation.index', 'match' => 'automation.*', 'icon' => 'bell']] : [['label' => 'Notifications', 'route' => 'workspace.notifications.index', 'match' => 'workspace.notifications.*', 'icon' => 'bell']]),
                ...((app(Modules::class)->enabled('integrations') && $user->canAny(['integrations.view', 'integrations.manage', 'integrations.tokens.manage', 'integrations.health.check'])) ? [['label' => 'Integrations', 'route' => 'integrations.index', 'match' => 'integrations.*', 'icon' => 'key']] : []),
                ['label' => 'Settings', 'route' => 'admin.index', 'match' => 'admin.*', 'icon' => 'settings'],
                ['label' => 'Audit', 'route' => 'identity.activity', 'match' => 'identity.activity', 'icon' => 'activity'],
                ...((app(Modules::class)->enabled('security') && $user->canAny(['security.view', 'security.manage', 'security.incidents.manage', 'security.backups.manage', 'security.retention.manage'])) ? [['label' => 'Backup & Monitoring', 'route' => 'security-center.index', 'match' => 'security-center.*', 'icon' => 'shield']] : []),
            ];
        }
        $groups['Workspace'][] = ['label' => 'My Profile', 'route' => 'workspace.profile.edit', 'match' => 'workspace.profile.*', 'icon' => 'users'];

        foreach ([
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

        $order = ['Workspace', 'CRM & Customer Management', 'Social Media Management', 'Commercial & Sales', 'Customer Experience', 'Human Resources', 'Operations & Projects', 'Procurement & Assets', 'Finance', 'Knowledge & Intelligence', 'Administration'];
        $orderedGroups = [];

        foreach ($order as $group) {
            if (! empty($groups[$group])) {
                $orderedGroups[$group] = $groups[$group];
            }
        }

        return $orderedGroups;
    }
}
