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
            $table->enum('day_of_week', ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'])->nullable()->after('frequency');
            $table->time('default_time')->nullable()->after('day_of_week');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rehearsal_schedules', function (Blueprint $table) {
            $table->dropColumn(['day_of_week', 'default_time']);
        });
    }
};
