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
        // Step 1: Drop the foreign key temporarily
        Schema::table('rosters', function (Blueprint $table) {
            try {
                $table->dropForeign(['band_id']);
            } catch (\Exception $e) {
                // Foreign key might not exist
            }
        });

        // Step 2: Drop the unique constraint
        Schema::table('rosters', function (Blueprint $table) {
            try {
                $table->dropUnique('unique_default_roster');
            } catch (\Exception $e) {
                // Constraint might not exist
            }
        });

        // Step 3: Re-add the foreign key and add regular index
        Schema::table('rosters', function (Blueprint $table) {
            try {
                $table->foreign('band_id')
                    ->references('id')
                    ->on('bands')
                    ->onDelete('cascade');
            } catch (\Exception $e) {
                // Foreign key might already exist
            }

            try {
                $table->index(['band_id', 'is_default']);
            } catch (\Exception $e) {
                // Index might already exist
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Optionally re-add the unique constraint (not recommended)
        // Schema::table('rosters', function (Blueprint $table) {
        //     $table->unique(['band_id', 'is_default'], 'unique_default_roster');
        // });
    }
};
