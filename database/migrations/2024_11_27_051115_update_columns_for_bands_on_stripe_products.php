<?php

use App\Models\Proposals;
use App\Models\StripeProducts;
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
        Schema::table('stripe_products', function (Blueprint $table) {
            // these will be linked to the band now instead of the proposal
            $table->renameColumn('proposal_id', 'band_id');
        });

        // pull the band ID from the proposal
        StripeProducts::all()->each(function ($stripeProduct) {
            $proposal = Proposals::find($stripeProduct->band_id);
            if (!$proposal) {
                echo "No proposal found for id $stripeProduct->band_id \n";
                return;
            }
            $stripeProduct->band_id = $proposal->band_id;
            $stripeProduct->save();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stripe_products', function (Blueprint $table) {
            $table->renameColumn('band_id', 'proposal_id');
        });
    }
};
