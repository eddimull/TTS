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
        Schema::create('venue_cache', function (Blueprint $table) {
            $table->id();
            $table->string('place_id')->unique()->nullable();
            $table->string('address')->nullable(); // For geocoding lookups by address
            $table->string('name')->nullable();
            $table->text('formatted_address')->nullable();
            $table->string('street_address')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('zip')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->json('raw_data')->nullable();
            $table->integer('usage_count')->default(1);
            $table->timestamp('last_used_at');
            $table->timestamps();

            $table->index('name');
            $table->index('place_id');
            $table->index('address');
            $table->index(['usage_count', 'last_used_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('venue_cache');
    }
};
