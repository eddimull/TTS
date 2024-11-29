<?php

use App\Models\Bookings;
use App\Models\Invoices;
use App\Models\Proposals;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->renameColumn('proposal_id', 'booking_id');
        });

        // update the booking_id to point to bookings instead of proposals
        Invoices::each(function ($invoice) {
            $proposal = Proposals::find($invoice->booking_id);
            if (!$proposal) {
                echo "No proposal found for id $invoice->booking_id \n";
                return;
            }
            $booking = Bookings::where([
                'band_id' => $proposal->band_id,
                'date' => Carbon::parse($proposal->date)->format('Y-m-d'),
                'name' => $proposal->name,
            ])->first();
            if (!$booking) {
                echo "No booking found for proposal id $invoice->booking_id \n";
                return;
            }
            $invoice->booking_id = $booking->id;
            $invoice->save();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->renameColumn('booking_id', 'proposal_id');
        });
    }
};
