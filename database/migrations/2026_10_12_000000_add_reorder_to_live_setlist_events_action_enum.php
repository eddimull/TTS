<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const ACTIONS = [
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
        'accept_suggestion',
        'session_break_start',
        'session_break_resume',
    ];

    public function up(): void
    {
        $this->setActions([...self::ACTIONS, 'reorder']);
    }

    public function down(): void
    {
        DB::table('live_setlist_events')->where('action', 'reorder')->delete();
        $this->setActions(self::ACTIONS);
    }

    private function setActions(array $actions): void
    {
        $list = implode(', ', array_map(fn ($a) => "'{$a}'", $actions));
        DB::statement("ALTER TABLE live_setlist_events MODIFY action ENUM({$list}) NOT NULL");
    }
};
