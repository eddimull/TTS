<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::firstOrCreate(['name' => 'read:lodging', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'write:lodging', 'guard_name' => 'web']);

        $ownerRole = Role::where('name', 'band-owner')->where('guard_name', 'web')->first();
        if ($ownerRole) {
            $ownerRole->givePermissionTo(['read:lodging', 'write:lodging']);
        }

        $memberRole = Role::where('name', 'band-member')->where('guard_name', 'web')->first();
        if ($memberRole) {
            $memberRole->givePermissionTo('read:lodging');
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        Permission::where('name', 'read:lodging')->where('guard_name', 'web')->get()->each->delete();
        Permission::where('name', 'write:lodging')->where('guard_name', 'web')->get()->each->delete();
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
