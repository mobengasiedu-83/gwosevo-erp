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
        $permissions = ['leads.view', 'leads.create', 'leads.update', 'leads.archive', 'leads.export', 'leads.approve', 'leads.assign', 'leads.qualify', 'leads.convert'];
        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Role::findOrCreate('Administrator', 'web')->givePermissionTo($permissions);
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
