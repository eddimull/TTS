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
        Schema::create('live_setlist_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->unique()->constrained('events')->cascadeOnDelete();
            $table->foreignId('band_id')->constrained('bands')->cascadeOnDelete();
            $table->foreignId('started_by')->constrained('users');
            $table->enum('status', ['pending', 'active', 'paused', 'completed'])->default('pending');
            $table->unsignedSmallInteger('current_position')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();

            $table->index('band_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('live_setlist_sessions');
    }
};
