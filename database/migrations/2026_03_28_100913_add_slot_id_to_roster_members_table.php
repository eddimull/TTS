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
        Schema::table('roster_members', function (Blueprint $table) {
            $table->foreignId('slot_id')
                ->nullable()
                ->after('roster_id')
                ->constrained('roster_slots')
                ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('roster_members', function (Blueprint $table) {
            $table->dropForeign(['slot_id']);
            $table->dropColumn('slot_id');
        });
    }
};
