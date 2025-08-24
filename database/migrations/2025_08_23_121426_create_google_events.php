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
        Schema::create('google_events', function (Blueprint $table) {
            $table->id();
            $table->string('google_event_id')->unique();
            $table->morphs('google_eventable');
            $table->integer('band_calendar_id')->constrained('band_calendars');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('google_events');
    }
};
