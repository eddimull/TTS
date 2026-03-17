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

        Permission::firstOrCreate(['name' => 'read:songs', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'write:songs', 'guard_name' => 'web']);

        // Give band-owner the new permissions
        $ownerRole = Role::where('name', 'band-owner')->where('guard_name', 'web')->first();
        if ($ownerRole) {
            $ownerRole->givePermissionTo(['read:songs', 'write:songs']);
        }

        // Give band-member read access by default
        // (Individual members can be granted write via the permissions UI)
        $memberRole = Role::where('name', 'band-member')->where('guard_name', 'web')->first();
        if ($memberRole) {
            $memberRole->givePermissionTo('read:songs');
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::where('name', 'read:songs')->where('guard_name', 'web')->get()->each->delete();
        Permission::where('name', 'write:songs')->where('guard_name', 'web')->get()->each->delete();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
