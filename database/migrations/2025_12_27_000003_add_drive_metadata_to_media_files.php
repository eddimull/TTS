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
        Schema::table('media_files', function (Blueprint $table) {
            $table->string('source')->default('upload')->after('disk'); // upload, google_drive
            $table->string('google_drive_file_id')->nullable()->after('source');
            $table->foreignId('drive_connection_id')
                ->nullable()
                ->after('google_drive_file_id')
                ->constrained('google_drive_connections')
                ->onDelete('set null');
            $table->timestamp('drive_last_modified')->nullable()->after('drive_connection_id');

            // Indexes
            $table->index(['source', 'band_id']);
            $table->index('google_drive_file_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('media_files', function (Blueprint $table) {
            $table->dropIndex(['source', 'band_id']);
            $table->dropIndex(['google_drive_file_id']);
            $table->dropForeign(['drive_connection_id']);
            $table->dropColumn(['source', 'google_drive_file_id', 'drive_connection_id', 'drive_last_modified']);
        });
    }
};
