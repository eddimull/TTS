<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The bulk delivered ack's whereExists probes
 * `created_at > last_delivered_at` per conversation on every list refetch —
 * without this composite index, long-lived channels get a near-full scan of
 * messages, and the no-op case (nothing new to ack, the endpoint's whole
 * optimization) is the worst hit since it still has to scan to prove
 * nothing qualifies.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->index(['conversation_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropIndex(['conversation_id', 'created_at']);
        });
    }
};
