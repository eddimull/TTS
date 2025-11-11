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
        Schema::table('stripe_customers', function (Blueprint $table) {
            $table->string('stripe_account_id')->nullable()->after('stripe_customer_id');
            
            // Add index for faster lookups
            $table->index(['contact_id', 'stripe_account_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stripe_customers', function (Blueprint $table) {
            $table->dropIndex(['contact_id', 'stripe_account_id']);
            $table->dropColumn('stripe_account_id');
        });
    }
};
