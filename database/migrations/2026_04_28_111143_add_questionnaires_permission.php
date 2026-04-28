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

        Permission::firstOrCreate(['name' => 'read:questionnaires', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'write:questionnaires', 'guard_name' => 'web']);

        $ownerRole = Role::where('name', 'band-owner')->where('guard_name', 'web')->first();
        if ($ownerRole) {
            $ownerRole->givePermissionTo(['read:questionnaires', 'write:questionnaires']);
        }

        $memberRole = Role::where('name', 'band-member')->where('guard_name', 'web')->first();
        if ($memberRole) {
            $memberRole->givePermissionTo('read:questionnaires');
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        Permission::where('name', 'read:questionnaires')->where('guard_name', 'web')->get()->each->delete();
        Permission::where('name', 'write:questionnaires')->where('guard_name', 'web')->get()->each->delete();
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
