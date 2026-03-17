<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('songs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('band_id')->constrained()->onDelete('cascade');
            $table->string('title');
            $table->string('artist')->nullable();
            $table->string('song_key', 20)->nullable();
            $table->string('genre', 100)->nullable();
            $table->unsignedSmallInteger('bpm')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('lead_singer_id')->nullable()->constrained('roster_members')->nullOnDelete();
            $table->foreignId('transition_song_id')->nullable()->constrained('songs')->nullOnDelete();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index('band_id');
            $table->index('active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('songs');
    }
};
