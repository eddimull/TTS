<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('rehearsal_schedules', function (Blueprint $table) {
            // Update frequency enum to match Google Calendar
            DB::statement("ALTER TABLE rehearsal_schedules MODIFY COLUMN frequency ENUM('daily', 'weekly', 'monthly', 'weekday', 'custom') DEFAULT 'weekly'");
            
            // Add field to store multiple selected days for weekly recurrence (JSON array)
            $table->json('selected_days')->nullable()->after('day_of_week');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rehearsal_schedules', function (Blueprint $table) {
            // Revert frequency enum
            DB::statement("ALTER TABLE rehearsal_schedules MODIFY COLUMN frequency ENUM('weekly', 'biweekly', 'monthly', 'custom') DEFAULT 'weekly'");
            
            $table->dropColumn('selected_days');
        });
    }
};
