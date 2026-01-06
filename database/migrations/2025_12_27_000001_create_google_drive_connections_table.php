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
        Schema::create('google_drive_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('band_id')->constrained()->onDelete('cascade');

            // OAuth credentials
            $table->text('access_token'); // Encrypted
            $table->text('refresh_token')->nullable(); // Encrypted
            $table->timestamp('token_expires_at')->nullable();
            $table->string('google_account_email'); // For display

            // Drive metadata
            $table->string('drive_id')->nullable(); // Google Drive ID

            // Sync control
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_synced_at')->nullable();
            $table->string('sync_status')->default('pending'); // pending, syncing, success, error
            $table->text('last_sync_error')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['band_id', 'is_active']);
            $table->index(['user_id', 'band_id']);
            $table->unique(['user_id', 'band_id', 'google_account_email'], 'unique_user_band_email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('google_drive_connections');
    }
};
