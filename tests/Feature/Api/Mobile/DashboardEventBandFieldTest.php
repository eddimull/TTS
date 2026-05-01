<?php

namespace Tests\Feature\Api\Mobile;

use App\Models\BandOwners;
use App\Models\Bands;
use App\Models\Bookings;
use App\Models\Events;
use App\Models\EventTypes;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardEventBandFieldTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_events_include_band_field(): void
    {
        $user = User::factory()->create();
        $band = Bands::create([
            'name'        => 'Test Band',
            'site_name'   => 'test-band-' . uniqid(),
            'is_personal' => false,
        ]);
        BandOwners::create(['user_id' => $user->id, 'band_id' => $band->id]);

        $eventType = EventTypes::factory()->create();
        $booking = Bookings::factory()->create([
            'name'   => 'Upcoming Gig',
            'date'   => now()->addDays(7)->toDateString(),
            'band_id' => $band->id,
            'status' => 'confirmed',
        ]);
        Events::factory()->create([
            'eventable_id'   => $booking->id,
            'eventable_type' => 'App\\Models\\Bookings',
            'event_type_id'  => $eventType->id,
            'date'           => now()->addDays(7)->toDateString(),
            'title'          => 'Upcoming Gig',
        ]);

        $token = $user->createToken('test')->plainTextToken;
        $response = $this->withToken($token)->getJson('/api/mobile/dashboard');
        $response->assertOk();

        $events = $response->json('events');
        $this->assertNotEmpty($events,
            'Dashboard should return at least one event for a user with an upcoming booking');

        $eventForOurBooking = collect($events)->firstWhere(
            fn ($e) => ($e['title'] ?? null) === 'Upcoming Gig'
        );
        $this->assertNotNull($eventForOurBooking,
            'Dashboard should surface the booking we just created as an event');

        $this->assertArrayHasKey('band', $eventForOurBooking,
            'Each dashboard event needs a nested band object for the mobile chip');
        $this->assertSame($band->id, $eventForOurBooking['band']['id']);
        $this->assertSame('Test Band', $eventForOurBooking['band']['name']);
        $this->assertFalse($eventForOurBooking['band']['is_personal']);
        $this->assertArrayHasKey('logo_url', $eventForOurBooking['band']);
    }

    public function test_dashboard_events_for_personal_band_include_is_personal_true(): void
    {
        $user = User::factory()->create();
        $personal = Bands::create([
            'name'        => "{$user->name}'s Band",
            'site_name'   => 'eddies-band-' . uniqid(),
            'is_personal' => true,
        ]);
        BandOwners::create(['user_id' => $user->id, 'band_id' => $personal->id]);

        $eventType = EventTypes::factory()->create();
        $booking = Bookings::factory()->create([
            'name'   => 'Church Sunday',
            'date'   => now()->addDays(3)->toDateString(),
            'band_id' => $personal->id,
            'status' => 'confirmed',
        ]);
        Events::factory()->create([
            'eventable_id'   => $booking->id,
            'eventable_type' => 'App\\Models\\Bookings',
            'event_type_id'  => $eventType->id,
            'date'           => now()->addDays(3)->toDateString(),
            'title'          => 'Church Sunday',
        ]);

        $token = $user->createToken('test')->plainTextToken;
        $response = $this->withToken($token)->getJson('/api/mobile/dashboard');
        $response->assertOk();

        $events = $response->json('events');
        $churchEvent = collect($events)->firstWhere(
            fn ($e) => ($e['title'] ?? null) === 'Church Sunday'
        );
        $this->assertNotNull($churchEvent);
        $this->assertTrue($churchEvent['band']['is_personal']);
    }

    public function test_band_lookup_uses_a_single_query_regardless_of_event_count(): void
    {
        $user = User::factory()->create();
        $bandA = Bands::create([
            'name' => 'A', 'site_name' => 'a-' . uniqid(), 'is_personal' => false,
        ]);
        $bandB = Bands::create([
            'name' => 'B', 'site_name' => 'b-' . uniqid(), 'is_personal' => false,
        ]);
        BandOwners::create(['user_id' => $user->id, 'band_id' => $bandA->id]);
        BandOwners::create(['user_id' => $user->id, 'band_id' => $bandB->id]);

        $eventType = EventTypes::factory()->create();

        // Five upcoming bookings split across two bands. Each booking gets its
        // own Events row so the dashboard surfaces them.
        $bookings = collect();
        for ($i = 0; $i < 5; $i++) {
            $band = $i % 2 === 0 ? $bandA : $bandB;
            $bookings->push(Bookings::factory()->create([
                'name'    => "Gig $i",
                'date'    => now()->addDays($i + 1)->toDateString(),
                'band_id' => $band->id,
                'status'  => 'confirmed',
            ]));
        }
        foreach ($bookings as $i => $booking) {
            Events::factory()->create([
                'title'          => "Gig $i",
                'date'           => $booking->date,
                'eventable_id'   => $booking->id,
                'eventable_type' => 'App\\Models\\Bookings',
                'event_type_id'  => $eventType->id,
            ]);
        }

        $token = $user->createToken('test')->plainTextToken;

        \DB::enableQueryLog();
        $response = $this->withToken($token)->getJson('/api/mobile/dashboard');
        $response->assertOk();
        $log = \DB::getQueryLog();
        \DB::disableQueryLog();

        // Pin the total query budget against `bands`. The dashboard hits `bands`
        // a fixed number of times for the user's bands list (band_owners/
        // band_members/band_subs joins inside UserEventsService — 3 queries,
        // O(1) regardless of event count) plus exactly 1 batch lookup from the
        // formatter. Total = 4. If the formatter regresses to per-event find(),
        // this count will jump (e.g., to 3 + 5 = 8 for 5 events).
        $bandSelectQueries = collect($log)
            ->filter(fn ($q) => str_contains($q['query'], 'from `bands`'))
            ->count();

        $this->assertSame(
            4,
            $bandSelectQueries,
            'Dashboard must hit `bands` exactly 4 times (3 O(1) user-band joins + 1 batched ' .
            "formatter lookup). Saw $bandSelectQueries queries against `bands`. " .
            'A jump in this count likely means the formatter regressed to per-event lookup. ' .
            'Query log: ' . json_encode($log, JSON_PRETTY_PRINT)
        );

        // Also pin the formatter's batch query specifically — the distinctive
        // shape `select * from bands where id in (...)` should occur exactly once.
        $batchLookupQueries = collect($log)
            ->filter(fn ($q) => str_contains($q['query'], 'select * from `bands` where `id` in'))
            ->count();

        $this->assertSame(
            1,
            $batchLookupQueries,
            "Formatter must issue exactly 1 batched bands lookup; saw $batchLookupQueries."
        );

        // Sanity-check the response actually has 5 events with bands.
        $events = $response->json('events');
        $eventsWithBand = collect($events)->filter(fn ($e) => isset($e['band']))->count();
        $this->assertGreaterThanOrEqual(5, $eventsWithBand);
    }
}
