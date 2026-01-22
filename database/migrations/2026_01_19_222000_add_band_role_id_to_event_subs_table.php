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
        Schema::table('event_subs', function (Blueprint $table) {
            $table->foreignId('band_role_id')->nullable()->after('band_id')->constrained()->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('event_subs', function (Blueprint $table) {
            $table->dropForeign(['band_role_id']);
            $table->dropColumn('band_role_id');
        });
    }
};
