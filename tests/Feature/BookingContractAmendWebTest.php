<?php

namespace Tests\Feature;

use App\Models\Bands;
use App\Models\Bookings;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BookingContractAmendWebTest extends TestCase
{
    use RefreshDatabase;

    public function test_web_amend_resets_booking_and_redirects_back(): void
    {
        Config::set('services.pandadoc.api_key', 'fake-api-key');
        Http::fake(['api.pandadoc.com/*' => Http::response([], 200)]);

        $user = User::factory()->create();
        $band = Bands::factory()->create();
        $band->owners()->create(['user_id' => $user->id]);

        $booking = Bookings::factory()->create([
            'band_id'         => $band->id,
            'status'          => 'pending',
            'contract_option' => 'default',
        ]);
        $booking->contract()->create([
            'author_id'   => $user->id,
            'status'      => 'sent',
            'envelope_id' => 'pd-doc-789',
        ]);

        $response = $this->actingAs($user)->post(
            route('Amend Booking Contract', ['band' => $band, 'booking' => $booking])
        );

        $response->assertRedirect();
        $this->assertSame('draft', $booking->fresh()->status);
        $this->assertSame('pending', $booking->fresh()->contract->status);
    }
}
