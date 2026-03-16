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
        Schema::create('live_setlist_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('live_setlist_sessions')->cascadeOnDelete();
            $table->foreignId('queue_entry_id')->nullable()->constrained('live_setlist_queue')->nullOnDelete();
            $table->foreignId('user_id')->constrained('users');
            $table->enum('action', [
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
            ]);
            $table->json('payload')->nullable();
            $table->timestamp('created_at');

            $table->index(['session_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('live_setlist_events');
    }
};
