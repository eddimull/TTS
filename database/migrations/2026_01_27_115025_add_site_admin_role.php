<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Create site-admin role for accessing Horizon and other admin tools
        Role::findOrCreate('site-admin', 'web');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove site-admin role
        $role = Role::findByName('site-admin', 'web');
        if ($role) {
            $role->delete();
        }
    }
};
