<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('questionnaire_instances', function (Blueprint $table) {
            // {song_id: must_play|do_not_play} — the band's resolution for a
            // song the client put on both lists. Read by ClientSongRequestsService.
            $table->json('song_overrides')->nullable()->after('locked_by_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('questionnaire_instances', function (Blueprint $table) {
            $table->dropColumn('song_overrides');
        });
    }
};
