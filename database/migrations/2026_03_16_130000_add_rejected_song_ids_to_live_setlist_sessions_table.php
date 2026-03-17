<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE live_setlist_sessions ADD COLUMN rejected_song_ids JSON NULL DEFAULT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE live_setlist_sessions DROP COLUMN rejected_song_ids");
    }
};
