<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class ApiPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            // Event permissions
            ['name' => 'api:read-events', 'guard_name' => 'api_token'],
            ['name' => 'api:write-events', 'guard_name' => 'api_token'],

            // Booking permissions
            ['name' => 'api:read-bookings', 'guard_name' => 'api_token'],
            ['name' => 'api:write-bookings', 'guard_name' => 'api_token'],
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate($permission);
        }

        $this->command->info('API permissions created successfully!');
    }
}
