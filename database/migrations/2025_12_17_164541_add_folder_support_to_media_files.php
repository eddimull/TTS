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
            $table->string('folder_path')->nullable()->after('media_type')->comment('Virtual folder path like "Photos/2024"');
            $table->index(['band_id', 'folder_path']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('media_files', function (Blueprint $table) {
            $table->dropIndex(['band_id', 'folder_path']);
            $table->dropColumn('folder_path');
        });
    }
};
