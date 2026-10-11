<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Developer (band API token) permission for POST /api/price-estimate.
 * Not granted to existing tokens — owners opt in per token.
 */
return new class extends Migration
{
    public function up(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::firstOrCreate(['name' => 'api:read-pricing', 'guard_name' => 'api_token']);

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        Permission::where('name', 'api:read-pricing')->where('guard_name', 'api_token')->get()->each->delete();
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
