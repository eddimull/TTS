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
        Schema::create('band_sub_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('band_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('band_role_id')->nullable()->constrained('band_roles')->nullOnDelete();

            // For non-registered users (before account creation)
            $table->string('email')->nullable();
            $table->string('name')->nullable();
            $table->string('phone')->nullable();

            // Invitation tracking
            $table->string('invitation_key', 36)->unique();
            $table->boolean('pending')->default(true);
            $table->timestamp('accepted_at')->nullable();

            // Notes specific to this band-level invitation
            $table->text('notes')->nullable();

            $table->timestamps();

            // A given email should only have one band-level invitation per band
            $table->unique(['band_id', 'email']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('band_sub_invitations');
    }
};
