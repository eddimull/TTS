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
        Schema::create('payouts', function (Blueprint $table) {
            $table->id();
            $table->morphs('payable'); // payable_type, payable_id (bookings, events, etc.)
            $table->foreignId('band_id')->constrained('bands')->onDelete('cascade');
            $table->foreignId('payout_config_id')->nullable()->constrained('band_payout_configs')->onDelete('set null');
            $table->bigInteger('base_amount'); // Base amount in cents
            $table->bigInteger('adjusted_amount'); // Adjusted amount after all adjustments (in cents)
            $table->json('calculation_result')->nullable(); // Store the full payout calculation
            $table->timestamps();
            $table->softDeletes();

            $table->index(['payable_type', 'payable_id', 'deleted_at']);
            $table->index(['band_id', 'deleted_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payouts');
    }
};
