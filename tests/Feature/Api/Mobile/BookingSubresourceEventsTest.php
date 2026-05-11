<?php

namespace Tests\Feature\Api\Mobile;

use App\Models\Bands;
use App\Models\Bookings;
use App\Models\Events;
use App\Models\EventTypes;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

/**
 * Feature coverage for the mobile API booking → events subresource:
 *  POST   /api/mobile/bands/{band}/bookings/{booking}/events
 *  PATCH  /api/mobile/bands/{band}/bookings/{booking}/events/{event}
 *  DELETE /api/mobile/bands/{band}/bookings/{booking}/events/{event}
 *
 * Plus regression coverage for the booking PATCH endpoint refusing the moved
 * date/venue fields.
 */
class BookingSubresourceEventsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Fake the bus so EventObserver / BookingObserver dispatches don't
        // leak file-cache unique-job locks between tests in the broader suite.
        // These tests don't assert on jobs, so faking is safe.
        Bus::fake();
    }

    /**
     * Build a user with one owned band, a booking, an initial event, and a
     * personal-access token. Mirrors the helper in BookingsTest.
     */
    private function makeBookingWithEvent(): array
    {
        $user = User::factory()->create();
        $band = Bands::factory()->create();
        $band->owners()->create(['user_id' => $user->id]);

        $booking = Bookings::factory()->create(['band_id' => $band->id]);

        $event = Events::factory()->create([
            'eventable_type' => Bookings::class,
            'eventable_id'   => $booking->id,
            'date'           => now()->addDays(30)->format('Y-m-d'),
        ]);

        $token = $user->createToken('test-device')->plainTextToken;

        return compact('user', 'band', 'booking', 'event', 'token');
    }

    // -------------------------------------------------------------------------
    // POST events.store
    // -------------------------------------------------------------------------

    public function test_can_create_event_for_booking(): void
    {
        ['band' => $band, 'booking' => $booking, 'token' => $token] = $this->makeBookingWithEvent();

        $eventType = EventTypes::firstOrCreate(['name' => 'Wedding']);

        $payload = [
            'title'         => 'Saturday Night Set',
            'date'          => now()->addDays(60)->format('Y-m-d'),
            'start_time'    => '20:00',
            'end_time'      => '23:00',
            'venue_name'    => 'House of Blues',
            'venue_address' => '225 Decatur St',
            'price'         => 1200,
            'event_type_id' => $eventType->id,
        ];

        $response = $this->withToken($token)
            ->withHeaders(['X-Band-ID' => $band->id])
            ->postJson("/api/mobile/bands/{$band->id}/bookings/{$booking->id}/events", $payload);

        $response->assertStatus(201);

        $this->assertDatabaseHas('events', [
            'eventable_type' => Bookings::class,
            'eventable_id'   => $booking->id,
            'title'          => 'Saturday Night Set',
            'venue_name'     => 'House of Blues',
        ]);

        // Booking should now have 2 events (the setUp event + the new one).
        $count = Events::where('eventable_type', Bookings::class)
            ->where('eventable_id', $booking->id)
            ->count();
        $this->assertSame(2, $count);
    }

    public function test_create_event_validates_required_fields(): void
    {
        ['band' => $band, 'booking' => $booking, 'token' => $token] = $this->makeBookingWithEvent();

        $response = $this->withToken($token)
            ->withHeaders(['X-Band-ID' => $band->id])
            ->postJson("/api/mobile/bands/{$band->id}/bookings/{$booking->id}/events", [
                // missing title and date
                'venue_name' => 'somewhere',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['title', 'date']);
    }

    // -------------------------------------------------------------------------
    // PATCH events.update
    // -------------------------------------------------------------------------

    public function test_can_update_event(): void
    {
        ['band' => $band, 'booking' => $booking, 'event' => $event, 'token' => $token] = $this->makeBookingWithEvent();

        $response = $this->withToken($token)
            ->withHeaders(['X-Band-ID' => $band->id])
            ->patchJson(
                "/api/mobile/bands/{$band->id}/bookings/{$booking->id}/events/{$event->key}",
                [
                    'title'      => 'Renamed via mobile',
                    'venue_name' => 'New Mobile Venue',
                ],
            );

        $response->assertOk();

        $this->assertDatabaseHas('events', [
            'id'         => $event->id,
            'title'      => 'Renamed via mobile',
            'venue_name' => 'New Mobile Venue',
        ]);
    }

    // -------------------------------------------------------------------------
    // DELETE events.destroy
    // -------------------------------------------------------------------------

    public function test_deleting_last_event_returns_422_and_keeps_event(): void
    {
        ['band' => $band, 'booking' => $booking, 'event' => $event, 'token' => $token] = $this->makeBookingWithEvent();

        // Sanity: booking has exactly one event.
        $this->assertSame(1, Events::where('eventable_type', Bookings::class)
            ->where('eventable_id', $booking->id)->count());

        $response = $this->withToken($token)
            ->withHeaders(['X-Band-ID' => $band->id])
            ->deleteJson("/api/mobile/bands/{$band->id}/bookings/{$booking->id}/events/{$event->key}");

        $response->assertStatus(422)
            ->assertJsonStructure(['error']);

        $this->assertDatabaseHas('events', ['id' => $event->id]);
    }

    public function test_can_delete_non_last_event(): void
    {
        ['band' => $band, 'booking' => $booking, 'token' => $token] = $this->makeBookingWithEvent();

        // Add a second event so the first is no longer the last.
        $extra = Events::factory()->create([
            'eventable_type' => Bookings::class,
            'eventable_id'   => $booking->id,
            'date'           => now()->addDays(31)->format('Y-m-d'),
        ]);

        $countBefore = Events::where('eventable_type', Bookings::class)
            ->where('eventable_id', $booking->id)->count();
        $this->assertSame(2, $countBefore);

        $response = $this->withToken($token)
            ->withHeaders(['X-Band-ID' => $band->id])
            ->deleteJson("/api/mobile/bands/{$band->id}/bookings/{$booking->id}/events/{$extra->key}");

        $response->assertOk();

        $this->assertDatabaseMissing('events', ['id' => $extra->id]);
        $countAfter = Events::where('eventable_type', Bookings::class)
            ->where('eventable_id', $booking->id)->count();
        $this->assertSame(1, $countAfter);
    }

    // -------------------------------------------------------------------------
    // Booking PATCH — prohibited fields
    // -------------------------------------------------------------------------

    public function test_booking_patch_rejects_date_field(): void
    {
        ['band' => $band, 'booking' => $booking, 'token' => $token] = $this->makeBookingWithEvent();

        $response = $this->withToken($token)
            ->withHeaders(['X-Band-ID' => $band->id])
            ->patchJson("/api/mobile/bands/{$band->id}/bookings/{$booking->id}", [
                'name' => 'Renamed',
                'date' => '2027-01-01',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['date']);
    }

    public function test_booking_patch_rejects_start_time_field(): void
    {
        ['band' => $band, 'booking' => $booking, 'token' => $token] = $this->makeBookingWithEvent();

        $response = $this->withToken($token)
            ->withHeaders(['X-Band-ID' => $band->id])
            ->patchJson("/api/mobile/bands/{$band->id}/bookings/{$booking->id}", [
                'name'       => 'Renamed',
                'start_time' => '20:00',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['start_time']);
    }

    public function test_booking_patch_rejects_venue_name_field(): void
    {
        ['band' => $band, 'booking' => $booking, 'token' => $token] = $this->makeBookingWithEvent();

        $response = $this->withToken($token)
            ->withHeaders(['X-Band-ID' => $band->id])
            ->patchJson("/api/mobile/bands/{$band->id}/bookings/{$booking->id}", [
                'name'       => 'Renamed',
                'venue_name' => 'Some Venue',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['venue_name']);
    }

    // -------------------------------------------------------------------------
    // Cross-band scoping (scopeBindings)
    // -------------------------------------------------------------------------

    public function test_cannot_update_event_for_booking_in_another_band(): void
    {
        // User owns Band A; booking lives on Band B (which they also happen to
        // own — the rejection here is purely about URL scoping, not membership).
        ['band' => $bandA, 'token' => $token, 'user' => $user] = $this->makeBookingWithEvent();

        $bandB = Bands::factory()->create();
        $bandB->owners()->create(['user_id' => $user->id]);

        $otherBooking = Bookings::factory()->create(['band_id' => $bandB->id]);
        $otherEvent = Events::factory()->create([
            'eventable_type' => Bookings::class,
            'eventable_id'   => $otherBooking->id,
            'date'           => now()->addDays(45)->format('Y-m-d'),
        ]);

        // URL says /bands/{bandA}/bookings/{otherBooking}/events/{otherEvent} —
        // that booking is not under bandA, so scopeBindings should refuse.
        $response = $this->withToken($token)
            ->withHeaders(['X-Band-ID' => $bandA->id])
            ->patchJson(
                "/api/mobile/bands/{$bandA->id}/bookings/{$otherBooking->id}/events/{$otherEvent->key}",
                ['title' => 'Should not work'],
            );

        $this->assertContains($response->getStatusCode(), [403, 404],
            'Cross-band booking access via subresource must be rejected');

        // The event must NOT have been updated.
        $this->assertDatabaseMissing('events', [
            'id'    => $otherEvent->id,
            'title' => 'Should not work',
        ]);
    }
}
