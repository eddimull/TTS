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
        Schema::create('google_drive_sync_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('connection_id')
                ->constrained('google_drive_connections')
                ->onDelete('cascade');
            $table->foreignId('folder_id')
                ->nullable()
                ->constrained('google_drive_folders')
                ->onDelete('cascade');

            $table->enum('sync_type', ['manual', 'scheduled', 'webhook']);
            $table->string('status'); // started, completed, failed, partial

            // Statistics
            $table->integer('files_checked')->default(0);
            $table->integer('files_downloaded')->default(0);
            $table->integer('files_updated')->default(0);
            $table->integer('files_deleted')->default(0);
            $table->integer('files_skipped')->default(0);
            $table->bigInteger('bytes_transferred')->default(0);

            // Error tracking
            $table->text('error_message')->nullable();
            $table->json('error_details')->nullable();

            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();

            $table->timestamps();

            // Indexes
            $table->index(['connection_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('google_drive_sync_logs');
    }
};
