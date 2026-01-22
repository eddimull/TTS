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
        // MySQL doesn't support adding enum values easily in Laravel,
        // so we need to use raw SQL

        // First add 'confirmed' to the enum
        DB::statement("ALTER TABLE event_members MODIFY COLUMN attendance_status ENUM('pending', 'confirmed', 'attended', 'absent', 'excused') DEFAULT 'attended'");

        // Update existing records to use 'confirmed' instead of 'pending' or 'attended'
        DB::table('event_members')->whereIn('attendance_status', ['pending', 'attended'])->update(['attendance_status' => 'confirmed']);

        // Remove 'pending' and set default to 'confirmed'
        DB::statement("ALTER TABLE event_members MODIFY COLUMN attendance_status ENUM('confirmed', 'attended', 'absent', 'excused') DEFAULT 'confirmed'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert back to original enum values
        DB::statement("ALTER TABLE event_members MODIFY COLUMN attendance_status ENUM('attended', 'absent', 'excused') DEFAULT 'attended'");
    }
};
