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
        Schema::create('setlist_songs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('setlist_id')->constrained('event_setlists')->cascadeOnDelete();
            $table->foreignId('song_id')->nullable()->constrained('songs')->nullOnDelete();
            $table->string('custom_title', 255)->nullable();
            $table->string('custom_artist', 255)->nullable();
            $table->unsignedSmallInteger('position');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['setlist_id', 'position']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('setlist_songs');
    }
};
