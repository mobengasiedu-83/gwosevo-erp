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

        $permissions = ['leads.view', 'leads.create', 'leads.update', 'leads.assign', 'leads.qualify', 'leads.convert'];
        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $adminRole = Role::findOrCreate('Administrator', 'web');
        $adminRole->givePermissionTo($permissions);
        $salesRole = Role::findOrCreate('Sales', 'web');
        if ($salesRole->wasRecentlyCreated) {
            $salesRole->givePermissionTo($permissions);
        }
        $this->call(IdentitySeeder::class);
        Organization::firstOrCreate(['code' => 'GWO-SEVO'], ['name' => 'GWO SEVO Solutions', 'timezone' => 'Africa/Accra']);
    }
}
