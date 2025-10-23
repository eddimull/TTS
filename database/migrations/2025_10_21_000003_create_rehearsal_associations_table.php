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
        // Pivot table for rehearsals <-> bookings/events relationships
        Schema::create('rehearsal_associations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rehearsal_id')->constrained('rehearsals')->onDelete('cascade');
            $table->morphs('associable'); // associable_type and associable_id for bookings or events
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->unique(['rehearsal_id', 'associable_type', 'associable_id'], 'rehearsal_associations_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rehearsal_associations');
    }
};
