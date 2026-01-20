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
        Schema::create('substitute_call_lists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('band_id')->constrained('bands')->onDelete('cascade');
            $table->string('instrument'); // Role/instrument name (e.g., 'Guitar', 'Drums', 'Oboe')
            $table->foreignId('roster_member_id')->constrained('roster_members')->onDelete('cascade');
            $table->integer('priority')->default(1); // Call order: 1st, 2nd, 3rd, etc.
            $table->text('notes')->nullable(); // Availability notes, special info
            $table->timestamps();

            // Index for efficient lookups
            $table->index(['band_id', 'instrument', 'priority']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('substitute_call_lists');
    }
};
