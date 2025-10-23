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
        Schema::table('rehearsal_schedules', function (Blueprint $table) {
            $table->unsignedTinyInteger('day_of_month')->nullable()->after('day_of_week');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rehearsal_schedules', function (Blueprint $table) {
            $table->dropColumn('day_of_month');
        });
    }
};
