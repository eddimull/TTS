<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('band_pricing_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('band_id')->unique()->constrained('bands')->cascadeOnDelete();
            $table->json('settings')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('band_pricing_settings');
    }
};
