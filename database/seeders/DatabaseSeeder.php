<?php

namespace Database\Seeders;

use App\Models\Organization;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
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

        $this->call(IdentitySeeder::class);
        Organization::firstOrCreate(['code' => 'GWO-SEVO'], ['name' => 'GWO SEVO Solutions', 'timezone' => 'Africa/Accra']);
    }
}
