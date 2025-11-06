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
        Schema::create('band_payout_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('band_id')->constrained('bands')->onDelete('cascade');
            $table->string('name')->default('Default Configuration');
            $table->boolean('is_active')->default(true);
            
            // Band's cut configuration
            $table->enum('band_cut_type', ['percentage', 'fixed', 'tiered', 'none'])->default('percentage');
            $table->decimal('band_cut_value', 10, 2)->default(0); // Percentage or fixed amount
            $table->json('band_cut_tier_config')->nullable(); // For tiered band cuts
            
            // Member payout configuration
            $table->enum('member_payout_type', ['equal_split', 'percentage', 'fixed', 'tiered'])->default('equal_split');
            
            // Tiered configuration (JSON for flexibility)
            // Example: [{"min": 0, "max": 5000, "type": "percentage", "value": 10}, {"min": 5001, "max": null, "type": "percentage", "value": 15}]
            $table->json('tier_config')->nullable();
            
            // Fixed member counts
            $table->integer('regular_member_count')->default(0);
            $table->integer('production_member_count')->default(0);
            
            // Member-specific percentages/amounts (JSON)
            // Example: [{"user_id": 1, "type": "owner", "value": 500}, {"user_id": 2, "type": "member", "value": 15}]
            $table->json('member_specific_config')->nullable();
            
            // Additional settings
            $table->boolean('include_owners')->default(true);
            $table->boolean('include_members')->default(true);
            $table->decimal('minimum_payout', 10, 2)->default(0);
            $table->text('notes')->nullable();
            
            $table->timestamps();
            
            // Indexes
            $table->index('band_id');
            $table->index(['band_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('band_payout_configs');
    }
};
