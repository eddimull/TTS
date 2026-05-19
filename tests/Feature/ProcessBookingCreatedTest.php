<?php

namespace Tests\Feature;

use App\Jobs\ProcessBookingCreated;
use App\Models\Bands;
use App\Models\Bookings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class ProcessBookingCreatedTest extends TestCase
{
    use RefreshDatabase;

    public function test_handle_skips_calendar_sync_when_band_has_no_booking_calendar(): void
    {
        $band = Bands::factory()->create();
        $booking = Bookings::factory()->create(['band_id' => $band->id]);

        $this->assertNull($band->bookingCalendar);

        Log::spy();

        (new ProcessBookingCreated($booking))->handle();

        Log::shouldHaveReceived('warning')
            ->withArgs(fn (string $message): bool => str_contains($message, 'has no booking calendar'))
            ->once();

        Log::shouldNotHaveReceived('error');

        $this->assertDatabaseMissing('google_events', [
            'google_eventable_id'   => $booking->id,
            'google_eventable_type' => Bookings::class,
        ]);
    }
}
