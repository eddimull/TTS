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
        Schema::create('roster_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('roster_id')->constrained('rosters')->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('cascade');

            // For non-user members (subs who aren't registered)
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();

            // Role/instrument for this roster
            $table->string('role')->nullable();

            // Default payout configuration for this person on this roster
            $table->enum('default_payout_type', ['equal_split', 'fixed', 'percentage'])->default('equal_split');
            $table->bigInteger('default_payout_amount')->nullable(); // In cents

            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();

            // Prevent duplicate entries
            $table->unique(['roster_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('roster_members');
    }
};
