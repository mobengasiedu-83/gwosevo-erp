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
        $permissions = ['leads.view', 'leads.create', 'leads.update', 'leads.archive', 'leads.export', 'leads.approve', 'leads.assign', 'leads.qualify', 'leads.convert', 'leads.disqualify', 'leads.import', 'leads.reports.view', 'leads.rules.manage', 'leads.duplicate.review', 'opportunities.view', 'opportunities.create', 'opportunities.update', 'opportunities.assign', 'opportunities.approve', 'opportunities.catalog.manage', 'opportunities.targets.manage', 'opportunities.reports.view', 'quotes.view', 'quotes.create', 'quotes.update', 'quotes.approve', 'contracts.view', 'contracts.create', 'contracts.update', 'contracts.templates.manage', 'contracts.onboarding.manage', 'contracts.handover', 'contracts.portal.manage', 'calls.view', 'calls.disposition', 'calls.configure', 'calls.dial', 'calls.recordings.view', 'calls.supervise', 'calls.qa.manage', ...$ticketPermissions, ...$smsPermissions];
        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        foreach ([
            'Administrator' => $permissions,
            'Sales' => ['leads.view', 'opportunities.view', 'opportunities.create', 'opportunities.update', 'opportunities.assign', 'opportunities.reports.view', 'quotes.view', 'quotes.create', 'quotes.update', 'contracts.view', 'contracts.create', 'contracts.update', 'contracts.onboarding.manage', 'contracts.handover', 'contracts.portal.manage'],
            'Finance' => ['leads.view', 'opportunities.view', 'opportunities.approve', 'opportunities.reports.view', 'quotes.view', 'quotes.approve', 'contracts.view'],
            'Read Only' => ['leads.view', 'opportunities.view'],
            'Support' => ['calls.view', 'calls.disposition'],
            'Operations' => ['leads.view', 'calls.view', 'calls.disposition', 'calls.supervise', 'contracts.view', 'contracts.onboarding.manage', 'contracts.handover'],
        ] as $name => $grants) {
            $role = Role::findOrCreate($name, 'web');
            if ($role->wasRecentlyCreated) {
                $role->givePermissionTo($grants);
            }
        }
        // Add the new ticket permissions to built-in roles without removing administrator custom grants.
        foreach ([
            'Administrator' => [...$ticketPermissions, 'calls.dial', 'sms.view'],
            'Support' => ['tickets.view', 'tickets.create', 'tickets.update', 'tickets.assign', 'tickets.reply', 'tickets.internal', 'tickets.manage', 'tickets.reports', 'tickets.portal'],
            'Operations' => ['tickets.view', 'tickets.create', 'tickets.update', 'tickets.assign', 'tickets.reply', 'tickets.internal', 'tickets.reports'],
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
