<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE live_setlist_sessions MODIFY status ENUM(
            'pending',
            'active',
            'paused',
            'break',
            'completed'
        ) NOT NULL DEFAULT 'pending'");

        DB::statement("ALTER TABLE live_setlist_sessions ADD COLUMN after_break TINYINT(1) NOT NULL DEFAULT 0");
        DB::statement("ALTER TABLE live_setlist_sessions ADD COLUMN break_started_at TIMESTAMP NULL DEFAULT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE live_setlist_sessions DROP COLUMN break_started_at");
        DB::statement("ALTER TABLE live_setlist_sessions DROP COLUMN after_break");

        DB::statement("ALTER TABLE live_setlist_sessions MODIFY status ENUM(
            'pending',
            'active',
            'paused',
            'completed'
        ) NOT NULL DEFAULT 'pending'");
    }
};
