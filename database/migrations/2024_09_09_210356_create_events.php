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
            $table->id();
            $table->foreignId('event_type_id')->constrained('event_types');
            $table->longText('notes')->nullable();
            $table->string('color')->nullable();
            $table->json('additional_data')->nullable();
            $table->morphs('eventable');
            $table->timestamps();
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
