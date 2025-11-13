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
        Schema::table('payments', function (Blueprint $table) {
            // Add polymorphic relationship for who made the payment (User or Contact)
            $table->string('payer_type')->nullable()->after('user_id');
            $table->unsignedBigInteger('payer_id')->nullable()->after('payer_type');
            $table->index(['payer_type', 'payer_id']);
            
            // Add payment type enum column
            $table->string('payment_type')->nullable()->after('payer_id');
        });

        // Migrate existing data - set payer to the user_id if it exists
        DB::table('payments')
            ->whereNotNull('user_id')
            ->update([
                'payer_type' => 'App\\Models\\User',
                'payer_id' => DB::raw('user_id')
            ]);
        
        // Set default payment type for existing payments
        // If they have an invoice, assume it was an invoice payment
        // Otherwise, mark as 'other' since we don't know
        DB::table('payments')
            ->whereNotNull('invoices_id')
            ->update(['payment_type' => 'invoice']);
            
        DB::table('payments')
            ->whereNull('invoices_id')
            ->update(['payment_type' => 'other']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['payer_type', 'payer_id']);
            $table->dropColumn(['payer_type', 'payer_id', 'payment_type']);
        });
    }
};
