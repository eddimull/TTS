<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('live_setlist_sessions', function (Blueprint $table) {
            // AI-parsed client song requests / notes from the booking's
            // questionnaire free text, captured when the session starts.
            $table->json('client_notes')->nullable()->after('rejected_song_ids');
        });
    }

    public function down(): void
    {
        Schema::table('live_setlist_sessions', function (Blueprint $table) {
            $table->dropColumn('client_notes');
        });
    }
};
