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
            $table->boolean('use_payment_groups')->default(false)->after('notes');
            // JSON structure: [
            //   {"group_id": 1, "allocation_type": "percentage", "allocation_value": 40},
            //   {"group_id": 2, "allocation_type": "fixed", "allocation_value": 1000}
            // ]
            $table->json('payment_group_config')->nullable()->after('use_payment_groups');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('band_payout_configs', function (Blueprint $table) {
            $table->dropColumn(['use_payment_groups', 'payment_group_config']);
        });
    }
};
