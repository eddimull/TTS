<?php

namespace Tests\Feature\Api\Mobile;

use App\Jobs\ProcessBookingDeleted;
use App\Jobs\ProcessEventDeleted;
use App\Models\BandOwners;
use App\Models\Bands;
use App\Models\Bookings;
use App\Models\Contacts;
use App\Models\Events;
use App\Models\EventTypes;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\Concerns\AccessesProtectedProperties;
use Tests\TestCase;

class BookingsTest extends TestCase
{
    use RefreshDatabase, AccessesProtectedProperties;

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function createUserWithBandAndBooking(string $status = 'confirmed'): array
    {
        $user = User::factory()->create();
        $band = Bands::factory()->create();
        $band->owners()->create(['user_id' => $user->id]);

        $booking = Bookings::factory()->create([
            'band_id' => $band->id,
            'status'  => $status,
        ]);

        $token = $user->createToken('test-device')->plainTextToken;

        return compact('user', 'band', 'booking', 'token');
    }

    // -------------------------------------------------------------------------
    // bookings.index
    // -------------------------------------------------------------------------

    public function test_bookings_index_requires_authentication(): void
    {
        $band = Bands::factory()->create();

        $this->getJson("/api/mobile/bands/{$band->id}/bookings")
            ->assertUnauthorized();
    }

    public function test_bookings_index_requires_band_header(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-device')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/mobile/bands/1/bookings')
            ->assertStatus(422);
    }

    public function test_bookings_index_returns_bookings_for_band(): void
    {
        [
            'band'    => $band,
            'booking' => $booking,
            'token'   => $token,
        ] = $this->createUserWithBandAndBooking();

        $response = $this->withToken($token)
            ->withHeaders(['X-Band-ID' => $band->id])
            ->getJson("/api/mobile/bands/{$band->id}/bookings");

        $response->assertOk()
            ->assertJsonStructure([
                'bookings' => [
                    '*' => [
                        'id', 'name', 'date', 'start_time', 'end_time',
                        'venue_name', 'venue_address', 'status', 'price',
                        'event_type_id', 'notes', 'amount_paid', 'amount_due',
                        'is_paid', 'contacts',
                    ],
                ],
            ]);

        $ids = collect($response->json('bookings'))->pluck('id');
        $this->assertTrue($ids->contains($booking->id));
    }

    public function test_bookings_index_filters_by_status(): void
    {
        [
            'band'  => $band,
            'token' => $token,
        ] = $this->createUserWithBandAndBooking('confirmed');

        // Create a pending booking in the same band
        $pendingBooking = Bookings::factory()->create([
            'band_id' => $band->id,
            'status'  => 'pending',
        ]);

        $response = $this->withToken($token)
            ->withHeaders(['X-Band-ID' => $band->id])
            ->getJson("/api/mobile/bands/{$band->id}/bookings?status=confirmed");

        $response->assertOk();

        $ids = collect($response->json('bookings'))->pluck('id');
        $this->assertFalse($ids->contains($pendingBooking->id));

        // All returned bookings should be confirmed
        $statuses = collect($response->json('bookings'))->pluck('status')->unique();
        $this->assertTrue($statuses->every(fn($s) => $s === 'confirmed'));
    }

    public function test_bookings_index_returns_403_for_non_member(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-device')->plainTextToken;

        $otherBand = Bands::factory()->create();

        $this->withToken($token)
            ->withHeaders(['X-Band-ID' => $otherBand->id])
            ->getJson("/api/mobile/bands/{$otherBand->id}/bookings")
            ->assertStatus(403);
    }

    // -------------------------------------------------------------------------
    // bookings.show
    // -------------------------------------------------------------------------

    public function test_bookings_show_returns_booking_detail(): void
    {
        [
            'band'    => $band,
            'booking' => $booking,
            'token'   => $token,
        ] = $this->createUserWithBandAndBooking();

        $response = $this->withToken($token)
            ->withHeaders(['X-Band-ID' => $band->id])
            ->getJson("/api/mobile/bands/{$band->id}/bookings/{$booking->id}");

        $response->assertOk()
            ->assertJsonStructure([
                'booking' => [
                    'id', 'name', 'date', 'start_time', 'end_time',
                    'venue_name', 'venue_address', 'status', 'price',
                    'event_type_id', 'notes', 'amount_paid', 'amount_due',
                    'is_paid', 'contacts', 'events',
                ],
            ]);

        $this->assertEquals($booking->id, $response->json('booking.id'));
    }

    public function test_bookings_show_includes_contacts_and_events(): void
    {
        [
            'band'    => $band,
            'booking' => $booking,
            'token'   => $token,
        ] = $this->createUserWithBandAndBooking();

        // Attach a contact
        $contact = Contacts::factory()->create(['band_id' => $band->id]);
        $booking->contacts()->attach($contact, [
            'role'       => 'primary',
            'is_primary' => true,
            'notes'      => null,
        ]);

        // Attach an event
        $eventType = EventTypes::factory()->create();
        Events::factory()->create([
            'eventable_id'   => $booking->id,
            'eventable_type' => 'App\\Models\\Bookings',
            'event_type_id'  => $eventType->id,
            'date'           => now()->addDays(10)->format('Y-m-d'),
        ]);

        $response = $this->withToken($token)
            ->withHeaders(['X-Band-ID' => $band->id])
            ->getJson("/api/mobile/bands/{$band->id}/bookings/{$booking->id}");

        $response->assertOk();

        $contacts = $response->json('booking.contacts');
        $this->assertNotEmpty($contacts);
        $this->assertEquals($contact->id, $contacts[0]['id']);

        $events = $response->json('booking.events');
        $this->assertNotEmpty($events);
        $this->assertArrayHasKey('key', $events[0]);
    }

    public function test_bookings_show_returns_404_for_wrong_band(): void
    {
        $user = User::factory()->create();
        $band = Bands::factory()->create();
        $band->owners()->create(['user_id' => $user->id]);
        $token = $user->createToken('test-device')->plainTextToken;

        // Booking that belongs to a different band
        $otherBand = Bands::factory()->create();
        $otherBand->owners()->create(['user_id' => $user->id]);
        $otherBooking = Bookings::factory()->create(['band_id' => $otherBand->id]);

        $this->withToken($token)
            ->withHeaders(['X-Band-ID' => $band->id])
            ->getJson("/api/mobile/bands/{$band->id}/bookings/{$otherBooking->id}")
            ->assertNotFound();
    }

    // -------------------------------------------------------------------------
    // bookings.store — URL {band} should override X-Band-ID header
    // -------------------------------------------------------------------------

    public function test_bookings_store_writes_to_url_band_not_header_band(): void
    {
        // Regression: when the user is "viewing" Band A (header) but creates a
        // booking under URL `/bands/{B}/bookings` (e.g. a personal gig from
        // Band A's dashboard), the booking must land on band B, not band A.
        $user = User::factory()->create();

        $headerBand = Bands::factory()->create();
        $headerBand->owners()->create(['user_id' => $user->id]);

        $urlBand = Bands::factory()->create();
        $urlBand->owners()->create(['user_id' => $user->id]);

        $eventType = EventTypes::factory()->create();
        $token = $user->createToken('test-device')->plainTextToken;

        $payload = [
            'name'          => 'Personal gig',
            'event_type_id' => $eventType->id,
            'date'          => '2030-01-15',
            'start_time'    => '20:00',
            'duration'      => 2,
        ];

        $this->withToken($token)
            ->withHeaders(['X-Band-ID' => $headerBand->id])
            ->postJson("/api/mobile/bands/{$urlBand->id}/bookings", $payload)
            ->assertCreated();

        // Booking should be on the URL-specified band, not the header band.
        $this->assertDatabaseHas('bookings', [
            'name'    => 'Personal gig',
            'band_id' => $urlBand->id,
        ]);
        $this->assertDatabaseMissing('bookings', [
            'name'    => 'Personal gig',
            'band_id' => $headerBand->id,
        ]);
    }

    public function test_destroy_dispatches_event_deletion_for_each_child_event(): void
    {
        [
            'band'    => $band,
            'booking' => $booking,
            'token'   => $token,
        ] = $this->createUserWithBandAndBooking();

        $eventType = EventTypes::factory()->create();

        $event1 = Events::withoutEvents(fn () => Events::factory()->create([
            'eventable_id'   => $booking->id,
            'eventable_type' => Bookings::class,
            'event_type_id'  => $eventType->id,
            'date'           => $booking->date,
            'title'          => 'Event 1',
        ]));

        $event2 = Events::withoutEvents(fn () => Events::factory()->create([
            'eventable_id'   => $booking->id,
            'eventable_type' => Bookings::class,
            'event_type_id'  => $eventType->id,
            'date'           => $booking->date,
            'title'          => 'Event 2',
        ]));

        Bus::fake();

        $response = $this->withToken($token)
            ->withHeaders(['X-Band-ID' => $band->id])
            ->deleteJson("/api/mobile/bands/{$band->id}/bookings/{$booking->id}");

        $response->assertOk()->assertJson(['message' => 'Booking deleted']);

        Bus::assertDispatched(ProcessEventDeleted::class, 2);
        Bus::assertDispatched(ProcessEventDeleted::class, fn ($job) =>
            $this->getProtectedProperty($job, 'event')->id === $event1->id
        );
        Bus::assertDispatched(ProcessEventDeleted::class, fn ($job) =>
            $this->getProtectedProperty($job, 'event')->id === $event2->id
        );
        Bus::assertDispatched(ProcessBookingDeleted::class, 1);
    }
}
