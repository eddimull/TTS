<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-user calendar feed token. This backs the unauthenticated ICS
     * subscription URL (/calendar/{token}.ics) that members add to Google
     * Calendar / Apple Calendar. It is minted lazily the first time a user
     * requests their feed URL and can be regenerated to revoke an old link,
     * so the column is nullable. Unique + indexed because every feed request
     * resolves the user by this token.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('calendar_token', 64)->nullable()->unique()->after('remember_token');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['calendar_token']);
            $table->dropColumn('calendar_token');
        });
    }
};
