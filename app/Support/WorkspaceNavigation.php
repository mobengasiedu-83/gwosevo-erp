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
        if (app(Modules::class)->enabled('crm') && $user->can('leads.view')) {
            $groups['Commercial & CRM'] = [['label' => 'Leads', 'route' => 'leads.index', 'match' => 'leads.*', 'icon' => 'users']];
        }
        if (app(Modules::class)->enabled('sales') && $user->can('opportunities.view')) {
            $groups['Commercial & CRM'][] = ['label' => 'Sales pipeline', 'route' => 'opportunities.index', 'match' => 'opportunities.*', 'icon' => 'activity'];
        }
        if (app(Modules::class)->enabled('contracts') && app(Modules::class)->enabled('sales') && ($user->can('quotes.view') || $user->can('contracts.view'))) {
            $groups['Commercial & CRM'][] = ['label' => 'Quotations & contracts', 'route' => $user->can('quotes.view') ? 'commercial.quotes.index' : 'commercial.contracts.index', 'match' => 'commercial.*', 'icon' => 'file'];
        }
        if (app(Modules::class)->enabled('marketing') && $user->can('marketing.view')) {
            $groups['Commercial & CRM'][] = ['label' => 'Campaigns & marketing', 'route' => 'marketing.index', 'match' => 'marketing.*', 'icon' => 'activity'];
        }
        if (app(Modules::class)->enabled('hr') && $user->can('hr.view')) {
            $groups['Human Resources'] = [['label' => 'Employee directory', 'route' => 'hr.index', 'match' => 'hr.*', 'icon' => 'users']];
        }
        if (app(Modules::class)->enabled('recruitment') && $user->can('recruitment.view')) {
            $groups['Human Resources'][] = ['label' => 'Recruitment & assessments', 'route' => 'recruitment.index', 'match' => 'recruitment.*', 'icon' => 'file'];
        }
        if (app(Modules::class)->enabled('performance') && $user->can('performance.view')) {
            $groups['Human Resources'][] = ['label' => 'Performance & appraisals', 'route' => 'performance.index', 'match' => 'performance.*', 'icon' => 'activity'];
        }
        if (app(Modules::class)->enabled('attendance') && ($user->can('attendance.view') || $user->can('attendance.self'))) {
            $groups['Human Resources'] ??= [];
            $groups['Human Resources'][] = ['label' => $user->can('attendance.view') ? 'Attendance & leave' : 'My attendance & leave', 'route' => 'attendance.index', 'match' => 'attendance.*', 'icon' => 'calendar'];
        }
        if (app(Modules::class)->enabled('projects') && ($user->can('projects.view') || $user->can('projects.manage') || $user->can('projects.tasks.manage'))) {
            $groups['Operations & Projects'] = [['label' => 'Projects & tasks', 'route' => 'projects.index', 'match' => 'projects.*', 'icon' => 'activity']];
        }
        if (app(Modules::class)->enabled('contact_centre') && $user->can('calls.view')) {
            $groups['Customer Experience'] = [['label' => 'Contact centre', 'route' => 'contact-centre.index', 'match' => 'contact-centre.*', 'icon' => 'phone']];
        }
        if (app(Modules::class)->enabled('support') && $user->can('tickets.view')) {
            $groups['Customer Experience'][] = ['label' => 'Support tickets', 'route' => 'tickets.index', 'match' => 'tickets.*', 'icon' => 'file'];
        }
        if (app(Modules::class)->enabled('sms') && $user->can('sms.view')) {
            $groups['Customer Experience'][] = ['label' => 'SMS & communications', 'route' => 'sms.index', 'match' => 'sms.*', 'icon' => 'message'];
        }
        $groups['Administration'] = [
            ['label' => 'Organization structure', 'route' => 'structure.index', 'match' => 'structure.*', 'icon' => 'users'],
            ['label' => 'Access reviews', 'route' => 'identity.reviews.index', 'match' => 'identity.reviews.*', 'icon' => 'file'],
            ['label' => 'Approval delegation', 'route' => 'identity.delegations.index', 'match' => 'identity.delegations.*', 'icon' => 'arrow'],
        ];
        if ($user->isSuperAdmin()) {
            $groups['Administration'] = array_merge($groups['Administration'], [
                ['label' => 'System configuration', 'route' => 'admin.index', 'match' => 'admin.*', 'icon' => 'settings'],
                ['label' => 'Users & access', 'route' => 'identity.users.index', 'match' => 'identity.users.*', 'icon' => 'users'],
                ['label' => 'Roles & permissions', 'route' => 'identity.roles.index', 'match' => 'identity.roles.*', 'icon' => 'shield'],
                ['label' => 'Audit activity', 'route' => 'identity.activity', 'match' => 'identity.activity', 'icon' => 'activity'],
                ['label' => 'SSO & email', 'route' => 'identity.integrations', 'match' => 'identity.integrations', 'icon' => 'key'],
            ]);
        }
        $groups['Workspace'][] = ['label' => 'My Profile & Security', 'route' => 'security.index', 'match' => 'security.*', 'icon' => 'shield'];

        $order = ['Workspace', 'Commercial & CRM', 'Customer Experience', 'Human Resources', 'Operations & Projects', 'Finance', 'Knowledge & Intelligence', 'Administration'];
        $orderedGroups = [];

        foreach ($order as $group) {
            if (! empty($groups[$group])) {
                $orderedGroups[$group] = $groups[$group];
            }
        }

        return $orderedGroups;
    }
}
