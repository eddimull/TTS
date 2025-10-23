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
            // Pattern types: 'day_of_month', 'first', 'second', 'third', 'fourth', 'last'
            $table->string('monthly_pattern')->nullable()->after('day_of_month');
            // When monthly_pattern is not 'day_of_month', this stores the weekday name
            $table->string('monthly_weekday')->nullable()->after('monthly_pattern');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rehearsal_schedules', function (Blueprint $table) {
            $table->dropColumn(['monthly_pattern', 'monthly_weekday']);
        });
    }
};
