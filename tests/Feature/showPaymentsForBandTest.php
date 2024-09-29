<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Bands;
use App\Models\Bookings;
use App\Models\Payments;
use App\Models\Proposals;
use App\Models\BandOwners;
use Illuminate\Support\Carbon;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;

class showPaymentsForBandTest extends TestCase
{
    use RefreshDatabase;
    /**
     * gets a list of payments for a band
     *
     * @return void
     */
    public function test_getAllPaymentsForBand()
    {
        $payments = 10;
        $user = User::factory()->create();
        $band = Bands::factory()->create();
        BandOwners::create([
            'band_id' => $band->id,
            'user_id' => $user->id
        ]);
        $booking = Bookings::factory()->forBand($band)->create();

        Payments::factory()->count($payments)->create([
            'name' => 'Test Payment',
            'date' => now(),
            'amount' => 500,
            'payable_id' => $booking->id,
            'payable_type' => Bookings::class,
            'band_id' => $band->id
        ]);


        $this->assertEquals($payments, $band->payments->count());
    }
}
