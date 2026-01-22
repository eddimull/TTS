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
        Schema::table('substitute_call_lists', function (Blueprint $table) {
            // Make roster_member_id nullable to support custom players
            $table->foreignId('roster_member_id')->nullable()->change();

            // Add custom player fields
            $table->string('custom_name')->nullable()->after('roster_member_id');
            $table->string('custom_email')->nullable()->after('custom_name');
            $table->string('custom_phone')->nullable()->after('custom_email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('substitute_call_lists', function (Blueprint $table) {
            $table->dropColumn(['custom_name', 'custom_email', 'custom_phone']);
            $table->foreignId('roster_member_id')->nullable(false)->change();
        });
    }
};
