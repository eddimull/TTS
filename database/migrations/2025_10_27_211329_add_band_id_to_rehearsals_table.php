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
        Schema::table('rehearsals', function (Blueprint $table) {
            // Add band_id after rehearsal_schedule_id for logical ordering
            $table->foreignId('band_id')->after('rehearsal_schedule_id')->constrained('bands')->onDelete('cascade');
            
            // Add index for better query performance
            $table->index('band_id');
        });
        
        // Backfill existing rehearsals with band_id from their schedule
        DB::statement('
            UPDATE rehearsals r
            INNER JOIN rehearsal_schedules rs ON r.rehearsal_schedule_id = rs.id
            SET r.band_id = rs.band_id
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rehearsals', function (Blueprint $table) {
            $table->dropForeign(['band_id']);
            $table->dropIndex(['band_id']);
            $table->dropColumn('band_id');
        });
    }
};
