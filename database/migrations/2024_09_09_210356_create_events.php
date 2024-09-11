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
        Schema::create('events', function (Blueprint $table)
        {
            $table->date('date');
            $table->foreignId('event_type_id')->constrained('event_types');
            $table->foreignId('band_id')->constrained('bands');
            $table->id();
            $table->json('additional_data')->nullable();
            $table->longText('notes')->nullable();
            $table->nullableMorphs('eventable');
            $table->text('title');
            $table->time('time')->nullable();
            $table->timestamps();
            $table->uuid('key')->unique();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
