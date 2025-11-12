<?php

namespace Tests\Feature;

use Carbon\Carbon;
use Tests\TestCase;
use App\Models\User;
use App\Models\Bands;
use App\Models\Bookings;
use App\Mail\PaymentMade;
use App\Models\Proposals;
use App\Enums\PaymentType;
use App\Models\BandOwners;
use App\Models\ProposalPayments;
use App\Policies\BookingsPolicy;
use Illuminate\Support\Facades\Mail;
use Database\Factories\BookingsFactory;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PaymentsTest extends TestCase
{


    /**
     * A basic feature test example.
     *
     * @return void
     */
    public function test_addPayment()
    {
        $user = User::factory()->create();
        $band = Bands::factory()->create();
        BandOwners::create([
            'band_id' => $band->id,
            'user_id' => $user->id
        ]);
        
        $booking = Bookings::factory()->forBand($band)->create();
        $paymentName = 'Test Payment';
        $response = $this->actingAs($user)->post(
            "bands/{$band->id}/booking/{$booking->id}/finances",
            [
                'name' => $paymentName,
                'date' => now(),
                'amount' => 500,
                'payment_type' => PaymentType::Cash->value,
            ]
        );

        $response->assertSessionHas(['successMessage']);
        $this->assertDatabaseHas('payments', [
            'name' => $paymentName,
            'payable_id' => $booking->id
        ]);
    }
    public function test_deletePayment()
    {

        $booking = Bookings::factory()->create([
            'price' => 100000
        ]);

        $owner = $booking->band->owner[0]->user;

        $paymentName = 'Should Be Deleted ' . Carbon::now()->timestamp;
        $payment = $booking->payments()->create([
            'name' => $paymentName,
            'date' => now(),
            'band_id' => $booking->band_id,
            'amount' => 500
        ]);

        $response = $this->actingAs($owner)->delete("/bands/{$booking->band->id}/booking/{$booking->id}/finances/" . $payment->id);
        $response->assertSessionHas(['successMessage']);

        $this->assertDatabaseMissing('payments', [
            'name' => $paymentName,
            'payable_id' => $booking->id
        ]);
    }

}
