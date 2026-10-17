<?php

namespace App\Support;

use App\Domain\Administration\Modules;
use App\Models\User;

class WorkspaceNavigation
{
    public function groups(User $user): array
    {
        $groups = ['Workspace' => [
            ['label' => 'Overview', 'route' => 'dashboard', 'match' => 'dashboard', 'icon' => 'grid'],
        ]];
        if (app(Modules::class)->enabled('crm') && $user->can('leads.view')) {
            $groups['Commercial'] = [['label' => 'CRM leads', 'route' => 'leads.index', 'match' => 'leads.*', 'icon' => 'users']];
        }
        $groups['Workspace'][] = ['label' => 'Organization structure', 'route' => 'structure.index', 'match' => 'structure.*', 'icon' => 'users'];
        $groups['Governance'] = [
            ['label' => 'Access reviews', 'route' => 'identity.reviews.index', 'match' => 'identity.reviews.*', 'icon' => 'file'],
            ['label' => 'Approval delegation', 'route' => 'identity.delegations.index', 'match' => 'identity.delegations.*', 'icon' => 'arrow'],
        ];
        if ($user->hasRole('Administrator')) {
            $groups['Administration'] = [
                ['label' => 'System configuration', 'route' => 'admin.index', 'match' => 'admin.*', 'icon' => 'settings'],
                ['label' => 'Users & access', 'route' => 'identity.users.index', 'match' => 'identity.users.*', 'icon' => 'users'],
                ['label' => 'Roles & permissions', 'route' => 'identity.roles.index', 'match' => 'identity.roles.*', 'icon' => 'shield'],
                ['label' => 'Audit activity', 'route' => 'identity.activity', 'match' => 'identity.activity', 'icon' => 'activity'],
                ['label' => 'SSO & email', 'route' => 'identity.integrations', 'match' => 'identity.integrations', 'icon' => 'key'],
            ];
        }
        $groups['Account'] = [['label' => 'My security', 'route' => 'security.index', 'match' => 'security.*', 'icon' => 'shield']];

        return $groups;
    }
}
