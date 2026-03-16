<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE live_setlist_events MODIFY action ENUM(
            'next',
            'thumbs_up',
            'thumbs_down',
            'skip',
            'skip_remove',
            'off_setlist',
            'promote_captain',
            'demote_captain',
            'session_start',
            'session_end',
            'session_pause',
            'session_resume',
            'ai_rerank',
            'accept_suggestion'
        ) NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE live_setlist_events MODIFY action ENUM(
            'next',
            'thumbs_up',
            'thumbs_down',
            'skip',
            'skip_remove',
            'off_setlist',
            'promote_captain',
            'demote_captain',
            'session_start',
            'session_end',
            'session_pause',
            'session_resume',
            'ai_rerank'
        ) NOT NULL");
    }
};
