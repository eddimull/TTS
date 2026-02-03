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
        // Drop pivot/junction tables first (foreign key constraints)
        Schema::dropIfExists('sent_proposals');
        Schema::dropIfExists('recurring_proposal_dates');
        Schema::dropIfExists('proposal_contacts');
        Schema::dropIfExists('proposal_contracts');
        Schema::dropIfExists('proposal_payments');

        // Drop main tables
        Schema::dropIfExists('proposals');
        Schema::dropIfExists('proposal_phases');

        // Remove foreign keys from other tables
        if (Schema::hasColumn('invoices', 'proposal_id')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->dropForeign(['proposal_id']);
                $table->dropColumn('proposal_id');
            });
        }

        if (Schema::hasColumn('stripe_customers', 'proposal_id')) {
            Schema::table('stripe_customers', function (Blueprint $table) {
                $table->dropForeign(['proposal_id']);
                $table->dropColumn('proposal_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Cannot reverse - data already migrated
        throw new \Exception('Cannot reverse proposal table deletion. Data has been migrated to bookings.');
    }
};
