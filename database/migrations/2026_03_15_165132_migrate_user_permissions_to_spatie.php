<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private array $resources = [
        'events', 'proposals', 'invoices', 'colors',
        'charts', 'bookings', 'rehearsals', 'media',
    ];

    public function up(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Create all 16 band permissions on the web guard
        foreach ($this->resources as $resource) {
            Permission::firstOrCreate(['name' => "read:{$resource}", 'guard_name' => 'web']);
            Permission::firstOrCreate(['name' => "write:{$resource}", 'guard_name' => 'web']);
        }

        // Create band roles (team-scoped, so team_id = null at role level — team scoping is on model_has_roles)
        $ownerRole = Role::firstOrCreate(['name' => 'band-owner', 'guard_name' => 'web']);
        $memberRole = Role::firstOrCreate(['name' => 'band-member', 'guard_name' => 'web']);

        // Give band-owner all 16 permissions
        $allBandPermissions = Permission::where('guard_name', 'web')
            ->whereIn('name', collect($this->resources)->flatMap(fn($r) => ["read:{$r}", "write:{$r}"])->toArray())
            ->get();
        $ownerRole->syncPermissions($allBandPermissions);

        // Migrate data: assign band-owner role for each band_owners entry
        $bandOwners = DB::table('band_owners')->get();
        foreach ($bandOwners as $row) {
            $user = \App\Models\User::find($row->user_id);
            if (!$user) {
                continue;
            }
            setPermissionsTeamId($row->band_id);
            if (!$user->hasRole('band-owner')) {
                $user->assignRole($ownerRole);
            }
        }

        // Migrate data: assign band-member role + specific permissions for each user_permissions entry
        if (Schema::hasTable('user_permissions')) {
            $rows = DB::table('user_permissions')->get();
            foreach ($rows as $row) {
                $user = \App\Models\User::find($row->user_id);
                if (!$user) {
                    continue;
                }
                setPermissionsTeamId($row->band_id);

                if (!$user->hasRole('band-member')) {
                    $user->assignRole($memberRole);
                }

                $permissionsToGrant = [];
                foreach ($this->resources as $resource) {
                    if (!empty($row->{"read_{$resource}"})) {
                        $permissionsToGrant[] = "read:{$resource}";
                    }
                    if (!empty($row->{"write_{$resource}"})) {
                        $permissionsToGrant[] = "write:{$resource}";
                    }
                }

                if (!empty($permissionsToGrant)) {
                    $user->syncPermissions($permissionsToGrant);
                }
            }
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Remove band-owner and band-member roles (cascades via FK to model_has_roles)
        Role::where('name', 'band-owner')->where('guard_name', 'web')->get()->each->delete();
        Role::where('name', 'band-member')->where('guard_name', 'web')->get()->each->delete();

        // Remove all band resource permissions
        foreach ($this->resources as $resource) {
            Permission::where('name', "read:{$resource}")->where('guard_name', 'web')->get()->each->delete();
            Permission::where('name', "write:{$resource}")->where('guard_name', 'web')->get()->each->delete();
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
