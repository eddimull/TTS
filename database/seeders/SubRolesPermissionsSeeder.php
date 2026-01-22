<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class SubRolesPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Create permissions for subs (using default 'web' guard)
        $permissions = [
            // View event details (location, time, attire, etc.)
            'view-event-details',

            // View and download charts for assigned events
            'view-charts',
            'download-charts',

            // View roster (members) without financial info
            'view-roster',

            // View own payout information
            'view-own-payout',

            // View event notes and details
            'view-event-notes',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web'
            ]);
        }

        // Create sub role
        $subRole = Role::firstOrCreate([
            'name' => 'sub',
            'guard_name' => 'web'
        ]);

        // Assign all sub permissions to the sub role
        $subRole->syncPermissions($permissions);

        $this->command->info('Sub role and permissions created successfully!');
        $this->command->info('Created role: sub');
        $this->command->info('Created ' . count($permissions) . ' permissions');
    }
}
