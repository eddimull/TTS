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
        Schema::create('rehearsals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rehearsal_schedule_id')->constrained('rehearsal_schedules')->onDelete('cascade');
            $table->string('venue_name')->nullable();
            $table->text('venue_address')->nullable();
            $table->text('notes')->nullable();
            $table->json('additional_data')->nullable(); // For flexible data like setlist, attendees, etc.
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rehearsals');
    }
};
