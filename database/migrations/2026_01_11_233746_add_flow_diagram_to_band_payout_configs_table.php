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
            $table->json('flow_diagram')
                ->nullable()
                ->after('notes')
                ->comment('Visual flow editor node/edge configuration');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('band_payout_configs', function (Blueprint $table) {
            $table->dropColumn('flow_diagram');
        });
    }
};
