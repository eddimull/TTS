<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A band can settle a booking's remaining balance without recording a payment.
 * The written-off amount (cents) counts toward "settled" (amount_due / is_paid)
 * but never toward amount_paid, revenue or payouts.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->unsignedInteger('written_off_amount')->default(0)->after('price');
            $table->timestamp('written_off_at')->nullable()->after('written_off_amount');
            $table->foreignId('written_off_by')->nullable()->after('written_off_at')
                ->constrained('users')->nullOnDelete();
            $table->string('write_off_note', 255)->nullable()->after('written_off_by');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('written_off_by');
            $table->dropColumn(['written_off_amount', 'written_off_at', 'write_off_note']);
        });
    }
};
