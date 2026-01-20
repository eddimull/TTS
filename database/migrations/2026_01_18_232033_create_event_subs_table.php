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
        Schema::create('event_subs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->onDelete('cascade');
            $table->foreignId('band_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade');

            // For non-registered users (before account creation)
            $table->string('email')->nullable();
            $table->string('name')->nullable();
            $table->string('phone')->nullable();

            // Invitation tracking
            $table->string('invitation_key', 36)->unique();
            $table->boolean('pending')->default(true);
            $table->timestamp('accepted_at')->nullable();

            // Payout information for this event
            $table->integer('payout_amount')->nullable()->comment('Amount in cents');

            // Notes specific to this sub for this event
            $table->text('notes')->nullable();

            $table->timestamps();

            // Ensure a user can't be added as sub to the same event multiple times
            $table->unique(['event_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('event_subs');
    }
};
