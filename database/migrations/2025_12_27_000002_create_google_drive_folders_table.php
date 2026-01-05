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
        Schema::create('google_drive_folders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('connection_id')
                ->constrained('google_drive_connections')
                ->onDelete('cascade');

            // Google Drive folder metadata
            $table->string('google_folder_id'); // Google Drive folder ID
            $table->string('google_folder_name'); // Name in Drive
            $table->text('google_folder_path')->nullable(); // Full path in Drive

            // Local mapping
            $table->string('local_folder_path')->nullable(); // Path in media_folders

            // Sync settings
            $table->boolean('auto_sync')->default(true);
            $table->timestamp('last_synced_at')->nullable();
            $table->string('sync_cursor')->nullable(); // For incremental sync (pageToken)

            $table->timestamps();

            // Indexes
            $table->index(['connection_id', 'google_folder_id']);
            $table->unique(['connection_id', 'google_folder_id'], 'unique_connection_folder');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('google_drive_folders');
    }
};
