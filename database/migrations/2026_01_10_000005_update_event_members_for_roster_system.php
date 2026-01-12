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
        // Check if columns already exist and add only if they don't
        if (!Schema::hasColumn('event_members', 'roster_member_id')) {
            Schema::table('event_members', function (Blueprint $table) {
                $table->foreignId('roster_member_id')->nullable()->after('user_id')->constrained('roster_members')->onDelete('cascade');
            });
        }

        if (Schema::hasColumn('event_members', 'is_band_member')) {
            Schema::table('event_members', function (Blueprint $table) {
                $table->dropColumn('is_band_member');
            });
        }

        if (!Schema::hasColumn('event_members', 'attendance_status')) {
            Schema::table('event_members', function (Blueprint $table) {
                $table->enum('attendance_status', ['attended', 'absent', 'excused'])->default('attended')->after('phone');
            });

            // Update existing records to use 'attended' status
            DB::table('event_members')->update(['attendance_status' => 'attended']);
        }

        if (Schema::hasColumn('event_members', 'status')) {
            Schema::table('event_members', function (Blueprint $table) {
                $table->dropColumn('status');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('event_members', function (Blueprint $table) {
            if (!Schema::hasColumn('event_members', 'status')) {
                $table->enum('status', ['playing', 'absent', 'substitute'])->default('playing')->after('phone');
            }
            if (!Schema::hasColumn('event_members', 'is_band_member')) {
                $table->boolean('is_band_member')->default(true)->after('status');
            }
        });

        Schema::table('event_members', function (Blueprint $table) {
            if (Schema::hasColumn('event_members', 'attendance_status')) {
                $table->dropColumn('attendance_status');
            }
            if (Schema::hasColumn('event_members', 'roster_member_id')) {
                $table->dropForeign(['roster_member_id']);
                $table->dropColumn('roster_member_id');
            }
        });
    }
};
