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
        $permissions = ['leads.view', 'leads.create', 'leads.update', 'leads.archive', 'leads.export', 'leads.approve', 'leads.assign', 'leads.qualify', 'leads.convert', 'leads.disqualify', 'leads.import', 'leads.reports.view', 'leads.rules.manage', 'leads.duplicate.review', 'opportunities.view', 'opportunities.create', 'opportunities.update', 'opportunities.assign', 'opportunities.approve', 'opportunities.catalog.manage', 'opportunities.targets.manage', 'opportunities.reports.view', 'quotes.view', 'quotes.create', 'quotes.update', 'quotes.approve', 'contracts.view', 'contracts.create', 'contracts.update', 'contracts.templates.manage', 'contracts.onboarding.manage', 'contracts.handover', 'contracts.portal.manage', 'calls.view', 'calls.disposition', 'calls.configure', 'calls.dial', 'calls.recordings.view', 'calls.supervise', 'calls.qa.manage'];
        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Role::findOrCreate('Administrator', 'web')->givePermissionTo($permissions);
        Role::findOrCreate('Sales', 'web')->givePermissionTo(['opportunities.view', 'opportunities.create', 'opportunities.update', 'opportunities.assign', 'opportunities.reports.view']);
        Role::findOrCreate('Finance', 'web')->givePermissionTo(['opportunities.view', 'opportunities.approve', 'opportunities.reports.view']);
        Role::findOrCreate('Read Only', 'web')->givePermissionTo(['opportunities.view']);
        Role::findOrCreate('Sales', 'web')->givePermissionTo(['quotes.view', 'quotes.create', 'quotes.update', 'contracts.view', 'contracts.create', 'contracts.update', 'contracts.onboarding.manage', 'contracts.handover', 'contracts.portal.manage']);
        Role::findOrCreate('Finance', 'web')->givePermissionTo(['quotes.view', 'quotes.approve', 'contracts.view']);
        Role::findOrCreate('Support', 'web')->givePermissionTo(['calls.view', 'calls.disposition']);
        Role::findOrCreate('Operations', 'web')->givePermissionTo(['calls.view', 'calls.disposition', 'calls.supervise']);
        Role::findOrCreate('Operations', 'web')->givePermissionTo(['contracts.view', 'contracts.onboarding.manage', 'contracts.handover']);
        foreach (['Sales', 'Operations', 'Human Resources', 'Finance', 'Support', 'Read Only'] as $name) {
            $role = Role::findOrCreate($name, 'web');
            // Never overwrite roles configured by an administrator on reseed.
            if ($role->wasRecentlyCreated) {
                $role->givePermissionTo('leads.view');
            }
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
