<?php

use App\Models\Bands;
use App\Models\User;
use App\Models\userPermissions;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    var array $permissionNames = [
        'read_events',
        'write_events',
        'read_proposals',
        'write_proposals',
        'read_invoices',
        'write_invoices',
        'read_colors',
        'write_colors',
        'read_charts',
        'write_charts',
        'read_bookings',
        'write_bookings',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // load all existing permissions
        $permissions = userPermissions::all();

        foreach ($this->permissionNames as $permissionName) {
            Permission::create(['name' => $permissionName]);
        }

        // assign the new permissions to users who have the old permissions
        foreach ($permissions as $permission) {
            $user = User::find($permission->user_id);
            $userPermissions = $user->permissionsForBand($permission->band_id);
            setPermissionsTeamId($userPermissions->band_id);
            $user->unsetRelation('roles')->unsetRelation('permissions');
            foreach ($this->permissionNames as $permissionName) {
                if ($userPermissions->$permissionName) {
                    $user->givePermissionTo($permissionName);
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // remove te previously created Spatie permissions
        foreach ($this->permissionNames as $permissionName) {
            Permission::where('name', $permissionName)->delete();
        }
    }
};
