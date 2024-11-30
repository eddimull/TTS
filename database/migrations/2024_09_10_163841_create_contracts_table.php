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
        Schema::create('contracts', function (Blueprint $table)
        {
            $table->id();
            $table->morphs('contractable');
            $table->string('envelope_id')->nullable();
            $table->foreignId('author_id')->constrained('users')->nullable();
            $table->enum('status', ['pending', 'sent', 'completed']);
            $table->string('asset_url')->nullable();
            $table->json('custom_terms')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contracts');
    }
};
