<?php

namespace Database\Seeders;

use App\Models\Organization;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = ['leads.view', 'leads.create', 'leads.update', 'leads.archive', 'leads.export', 'leads.approve', 'leads.assign', 'leads.qualify', 'leads.convert', 'leads.disqualify', 'leads.import', 'leads.reports.view', 'leads.rules.manage', 'leads.duplicate.review', 'opportunities.view', 'opportunities.create', 'opportunities.update', 'opportunities.assign', 'opportunities.approve', 'opportunities.catalog.manage', 'opportunities.targets.manage', 'opportunities.reports.view'];
        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $adminRole = Role::findOrCreate('Administrator', 'web');
        $adminRole->givePermissionTo($permissions);
        $salesRole = Role::findOrCreate('Sales', 'web');
        $salesRole->givePermissionTo(['opportunities.view', 'opportunities.create', 'opportunities.update', 'opportunities.assign', 'opportunities.reports.view']);
        Role::findOrCreate('Finance', 'web')->givePermissionTo(['opportunities.view', 'opportunities.approve', 'opportunities.reports.view']);
        Role::findOrCreate('Read Only', 'web')->givePermissionTo(['opportunities.view']);
        if ($salesRole->wasRecentlyCreated) {
            $salesRole->givePermissionTo(['leads.view', 'leads.create', 'leads.update', 'leads.assign', 'leads.qualify', 'leads.convert', 'leads.disqualify']);
        }
        $this->call(IdentitySeeder::class);
        Organization::firstOrCreate(['code' => 'GWO-SEVO'], ['name' => 'GWO SEVO Solutions', 'timezone' => 'Africa/Accra']);
    }
}
