<?php

namespace Tests\Feature;

use App\Models\Events;
use App\Models\Rehearsal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Regression coverage for venue/address resolution on the public-facing
 * advance page and its Google static map.
 *
 * Migration 2026_05_03_140000_move_date_venue_from_bookings_to_events moved
 * venue_name/venue_address off the bookings table and onto the events row.
 * The advance view and the locationImage controller still read those columns
 * from $event->eventable (a Bookings), which now returns null — leaving the
 * venue block blank and breaking the static map (g.co/staticmaperror).
 */
class AdvancePageMapsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Event/booking observers dispatch unique jobs on write; we do not
        // assert on them here, so faking keeps the suite deterministic.
        Bus::fake();

        // getGoogleMapsImage() caches the static-map response; flush so each
        // test exercises a fresh fetch.
        Cache::flush();
    }

    public function test_resolved_venue_accessors_read_from_event_row(): void
    {
        $event = Events::factory()->create([
            'venue_name'    => 'Chateau Country Club',
            'venue_address' => '3600 Chateau Blvd, Kenner LA 70065',
        ]);

        $this->assertSame('Chateau Country Club', $event->resolved_venue_name);
        $this->assertSame('3600 Chateau Blvd, Kenner LA 70065', $event->resolved_venue_address);
    }

    public function test_resolved_venue_accessors_fall_back_to_eventable(): void
    {
        $rehearsal = Rehearsal::factory()->create([
            'venue_name'    => 'Practice Spot',
            'venue_address' => '55 Studio Way, Metairie LA 70001',
        ]);

        $event = Events::factory()->create([
            'eventable_type' => Rehearsal::class,
            'eventable_id'   => $rehearsal->id,
            'venue_name'     => null,
            'venue_address'  => null,
        ]);

        $this->assertSame('Practice Spot', $event->resolved_venue_name);
        $this->assertSame('55 Studio Way, Metairie LA 70001', $event->resolved_venue_address);
    }

    public function test_resolved_venue_accessors_prefer_event_row_over_eventable(): void
    {
        $rehearsal = Rehearsal::factory()->create([
            'venue_name'    => 'Stale Eventable Venue',
            'venue_address' => 'Stale Eventable Address',
        ]);

        $event = Events::factory()->create([
            'eventable_type' => Rehearsal::class,
            'eventable_id'   => $rehearsal->id,
            'venue_name'     => 'Event Row Venue',
            'venue_address'  => 'Event Row Address',
        ]);

        $this->assertSame('Event Row Venue', $event->resolved_venue_name);
        $this->assertSame('Event Row Address', $event->resolved_venue_address);
    }

    public function test_resolved_venue_accessors_return_null_when_unset(): void
    {
        $event = Events::factory()->create([
            'venue_name'    => null,
            'venue_address' => null,
        ]);

        $this->assertNull($event->resolved_venue_name);
        $this->assertNull($event->resolved_venue_address);
    }

    public function test_advance_page_renders_venue_from_event_row(): void
    {
        $this->withoutVite();

        $event = Events::factory()->create([
            'venue_name'    => 'Chateau Country Club',
            'venue_address' => '3600 Chateau Blvd, Kenner LA 70065',
        ]);

        $response = $this->actingAs(User::factory()->create())
            ->get('/events/' . $event->key . '/advance');

        $response->assertOk();
        $response->assertSee('Chateau Country Club');
        $response->assertSee('Kenner, LA 70065');

        // The Google/Apple Maps deep links use the raw, unparsed venue string.
        $response->assertSee(
            urlencode('Chateau Country Club 3600 Chateau Blvd, Kenner LA 70065'),
            false
        );
    }

    public function test_advance_page_renders_show_time_from_event_start_time(): void
    {
        $this->withoutVite();

        // The 2026_05_03 migration dropped events.time in favour of
        // start_time. The advance schedule still read $event->time, so the
        // Show Time row rendered as N/A.
        $event = Events::factory()->create([
            'start_time'    => '19:30',
            'venue_name'    => 'Chateau Country Club',
            'venue_address' => '3600 Chateau Blvd, Kenner LA 70065',
        ]);

        $response = $this->actingAs(User::factory()->create())
            ->get('/events/' . $event->key . '/advance');

        $response->assertOk();
        $response->assertSee('Show Time');
        $response->assertSee('7:30 PM');
    }

    public function test_location_image_requests_static_map_with_event_row_venue(): void
    {
        Http::fake();

        $event = Events::factory()->create([
            'venue_name'    => 'Chateau Country Club',
            'venue_address' => '3600 Chateau Blvd, Kenner LA 70065',
        ]);

        $response = $this->actingAs(User::factory()->create())
            ->get('/events/' . $event->key . '/locationImage');

        $response->assertOk();

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'maps.googleapis.com/maps/api/staticmap')
                && str_contains(
                    $request->url(),
                    urlencode('Chateau Country Club 3600 Chateau Blvd, Kenner LA 70065')
                );
        });
    }
}
