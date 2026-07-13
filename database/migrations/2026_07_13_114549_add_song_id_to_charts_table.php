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
        Schema::table('charts', function (Blueprint $table) {
            $table->foreignId('song_id')
                ->nullable()
                ->after('band_id')
                ->constrained('songs')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('charts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('song_id');
        });
    }
};
