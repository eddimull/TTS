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
        Schema::create('band_payment_group_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('band_payment_group_id')->constrained('band_payment_groups')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->enum('payout_type', ['percentage', 'fixed', 'equal_split'])->nullable(); // Override group default
            $table->decimal('payout_value', 10, 2)->nullable(); // Override group default
            $table->text('notes')->nullable();
            $table->timestamps();
            
            // Indexes
            $table->index('band_payment_group_id');
            $table->index('user_id');
            $table->unique(['band_payment_group_id', 'user_id']); // User can only be in a group once
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('band_payment_group_members');
    }
};
