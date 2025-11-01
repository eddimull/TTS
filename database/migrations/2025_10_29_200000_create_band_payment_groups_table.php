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
        Schema::create('band_payment_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('band_id')->constrained('bands')->onDelete('cascade');
            $table->string('name'); // e.g., "Sound Crew", "Lighting", "Dancers", "Players"
            $table->text('description')->nullable();
            $table->enum('default_payout_type', ['percentage', 'fixed', 'equal_split'])->default('equal_split');
            $table->decimal('default_payout_value', 10, 2)->nullable(); // Default percentage or fixed amount
            $table->integer('display_order')->default(0); // For sorting in UI
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            // Indexes
            $table->index('band_id');
            $table->index(['band_id', 'is_active']);
            $table->unique(['band_id', 'name']); // Unique group names per band
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('band_payment_groups');
    }
};
