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
        Schema::create('live_setlist_queue', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('live_setlist_sessions')->cascadeOnDelete();
            $table->foreignId('song_id')->nullable()->constrained('songs')->nullOnDelete();
            $table->string('custom_title', 255)->nullable();
            $table->string('custom_artist', 255)->nullable();
            $table->unsignedSmallInteger('position');
            $table->enum('status', ['pending', 'played', 'skipped', 'removed'])->default('pending');
            $table->timestamp('played_at')->nullable();
            $table->boolean('is_off_setlist')->default(false);
            $table->enum('crowd_reaction', ['positive', 'negative', 'neutral'])->nullable();
            $table->decimal('ai_weight', 5, 2)->default(1.00);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['session_id', 'position']);
            $table->index(['session_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('live_setlist_queue');
    }
};
