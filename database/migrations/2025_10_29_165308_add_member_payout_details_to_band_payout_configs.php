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
        Schema::table('band_payout_configs', function (Blueprint $table) {
            // Add columns for production member types
            // JSON structure: [
            //   {"name": "Sound Engineer", "type": "fixed", "value": 500},
            //   {"name": "Lighting Tech", "type": "percentage", "value": 10}
            // ]
            $table->json('production_member_types')->nullable()->after('production_member_count');
            
            // Enhance member_specific_config to store more details
            // JSON structure: [
            //   {"user_id": 1, "member_type": "owner", "name": "John Doe", "payout_type": "percentage", "value": 15},
            //   {"user_id": 2, "member_type": "member", "name": "Jane Smith", "payout_type": "fixed", "value": 400}
            // ]
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('band_payout_configs', function (Blueprint $table) {
            $table->dropColumn('production_member_types');
        });
    }
};
