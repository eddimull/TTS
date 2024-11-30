<?php

namespace Tests\Feature;

use App\Mail\PaymentMade;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\Bands;
use App\Models\Proposals;
use App\Models\BandOwners;
use App\Models\Bookings;
use App\Models\ProposalPayments;
use App\Policies\BookingsPolicy;
use Carbon\Carbon;
use Database\Factories\BookingsFactory;
use Illuminate\Support\Facades\Mail;

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
        // $proposal = Proposals::factory()->create([
        //     'band_id'=>$band->id,
        //     'author_id'=>$user->id,
        //     'phase_id'=>6
        // ]);
        $booking = Bookings::factory()->forBand($band)->create();
        $paymentName = 'Test Payment';
        $response = $this->actingAs($user)->post(
            "bands/{$band->id}/booking/{$booking->id}/finances",
            [
                'name' => $paymentName,
                'date' => now(),
                'amount' => 500
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


    public function test_paymentEmailSent()
    {
        Mail::fake();
        $payment = ProposalPayments::factory()->create();
        Mail::send(new PaymentMade($payment));
        Mail::assertSent(PaymentMade::class);
    }



    public function test_bandOwnerCanGetReceipt()
    {
        $payment = ProposalPayments::factory()->create();

        $bandOwner = $payment->proposal->band->owner[0]->user;
        $response = $this->actingAs($bandOwner)->get('/proposals/' . $payment->proposal->key . '/downloadReceipt');

        $this->assertTrue($response->headers->get('content-type') == 'application/pdf');
    }
}
