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
        Schema::create('rehearsal_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('band_id')->constrained('bands')->onDelete('cascade');
            $table->string('name'); // e.g., "Weekly Practice", "Pre-Tour Rehearsals"
            $table->text('description')->nullable();
            $table->enum('frequency', ['weekly', 'biweekly', 'monthly', 'custom'])->default('weekly');
            $table->string('location_name')->nullable();
            $table->text('location_address')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rehearsal_schedules');
    }
};
