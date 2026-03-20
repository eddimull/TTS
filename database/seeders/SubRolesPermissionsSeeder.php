<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
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

        $permissions = [
            'read:events',
            'read:charts',
            'read:songs',
        ];

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
