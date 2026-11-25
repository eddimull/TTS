<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The client's questionnaire free text, read by the AI into song requests,
 * rules and notes. One row per booking, refreshed by a queued job whenever
 * the submitted answers change.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_client_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->unique()->constrained('bookings')->cascadeOnDelete();
            $table->json('notes');
            // sha1 of the answers the notes were read from.
            $table->char('source_hash', 40);
            $table->timestamp('parsed_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_client_notes');
    }
};
