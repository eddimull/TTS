<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mobile attire chips — short reusable dress-code labels (e.g. "All black",
 * "Black tie") that a band keeps so they can be applied to events with one
 * tap. Uniqueness is enforced per-band; we normalize whitespace and case at
 * the application layer (trim + case-insensitive compare in the controller),
 * so the unique index is on the literal label as stored.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attire_chips', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('band_id');
            $table->string('label', 64);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->foreign('band_id')
                ->references('id')->on('bands')
                ->cascadeOnDelete();

            $table->unique(['band_id', 'label']);
            $table->index(['band_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attire_chips');
    }
};
