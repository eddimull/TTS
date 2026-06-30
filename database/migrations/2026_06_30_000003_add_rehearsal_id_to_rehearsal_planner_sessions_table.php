<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rehearsal_planner_sessions', function (Blueprint $table) {
            $table->foreignId('rehearsal_id')
                ->nullable()
                ->after('user_id')
                ->constrained('rehearsals')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('rehearsal_planner_sessions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('rehearsal_id');
        });
    }
};
