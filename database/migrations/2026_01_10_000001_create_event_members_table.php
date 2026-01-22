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
        Schema::create('event_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->onDelete('cascade');
            $table->foreignId('band_id')->constrained('bands')->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('cascade');

            // For non-member substitutes
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();

            // Status: 'playing', 'absent', 'substitute'
            $table->enum('status', ['playing', 'absent', 'substitute'])->default('playing');

            // Whether this person is a regular band member
            $table->boolean('is_band_member')->default(true);

            // Optional custom payout amount (in cents)
            $table->bigInteger('payout_amount')->nullable();

            // Notes about this member for this event
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Ensure we don't have duplicate entries for the same user/event
            $table->unique(['event_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('event_members');
    }
};
