<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('push_notification_log', function (Blueprint $table) {
            $table->unsignedBigInteger('event_id')->nullable()->change();
            $table->string('dedupe_key', 120)->nullable()->after('type');
        });

        // Backfill so leave-by's dedupe pre-checks keep seeing historical sends.
        DB::statement(
            "UPDATE push_notification_log SET dedupe_key = CONCAT('event:', event_id, ':', type) WHERE dedupe_key IS NULL"
        );

        Schema::table('push_notification_log', function (Blueprint $table) {
            $table->unique(['user_id', 'dedupe_key']);
        });
    }

    public function down(): void
    {
        Schema::table('push_notification_log', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'dedupe_key']);
            $table->dropColumn('dedupe_key');
        });
        // event_id stays nullable on rollback: restoring NOT NULL would fail
        // if generic rows were written. Acceptable for a dev rollback.
    }
};
