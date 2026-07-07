<?php

namespace Tests\Feature\Broadcasting;

use App\Events\BandDataChanged;
use App\Models\Bands;
use App\Models\Bookings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class BroadcastsBandChangesTest extends TestCase
{
    use RefreshDatabase;

    public function test_booking_create_update_delete_each_broadcast_a_band_signal(): void
    {
        Event::fake([BandDataChanged::class]);
        $band = Bands::factory()->create();

        $booking = Bookings::factory()->create(['band_id' => $band->id]);
        Event::assertDispatched(
            BandDataChanged::class,
            fn (BandDataChanged $e) => $e->bandId === $band->id
                && $e->model === 'bookings'
                && $e->id === $booking->id
                && $e->action === 'created'
                && $e->parent === null,
        );

        $booking->update(['name' => 'Renamed booking']);
        Event::assertDispatched(
            BandDataChanged::class,
            fn (BandDataChanged $e) => $e->id === $booking->id && $e->action === 'updated',
        );

        $booking->delete();
        Event::assertDispatched(
            BandDataChanged::class,
            fn (BandDataChanged $e) => $e->id === $booking->id && $e->action === 'deleted',
        );
    }
}
