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
        Schema::table('setlist_songs', function (Blueprint $table) {
            $table->enum('type', ['song', 'break'])->default('song')->after('id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('setlist_songs', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
