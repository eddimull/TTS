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
        // Drop and recreate tables with correct column types
        Schema::dropIfExists('payout_adjustments');
        Schema::dropIfExists('payouts');
        
        // Recreate payouts table
        Schema::create('payouts', function (Blueprint $table) {
            $table->id();
            $table->morphs('payable');
            $table->foreignId('band_id')->constrained('bands')->onDelete('cascade');
            $table->foreignId('payout_config_id')->nullable()->constrained('band_payout_configs')->onDelete('set null');
            $table->bigInteger('base_amount');
            $table->bigInteger('adjusted_amount');
            $table->json('calculation_result')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['payable_type', 'payable_id', 'deleted_at']);
            $table->index(['band_id', 'deleted_at']);
        });
        
        // Recreate payout_adjustments table
        Schema::create('payout_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payout_id')->constrained('payouts')->onDelete('cascade');
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->bigInteger('amount');
            $table->string('description');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['payout_id', 'deleted_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payout_adjustments');
        Schema::dropIfExists('payouts');
    }
};
