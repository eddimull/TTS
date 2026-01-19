<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('roster_members', function (Blueprint $table) {
            $table->foreignId('band_role_id')->nullable()->after('role')->constrained('band_roles')->onDelete('set null');
            $table->index('band_role_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('roster_members', function (Blueprint $table) {
            $table->dropForeign(['band_role_id']);
            $table->dropColumn('band_role_id');
        });
    }
};
