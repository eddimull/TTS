<?php

namespace Tests\Feature;

use App\Models\Bands;
use App\Models\Bookings;
use App\Models\Events;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MobileStatsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Bands $band;

    protected function setUp(): void
    {
        parent::setUp();

        // Pin the clock so the earned-vs-upcoming split (gig date vs "today") is
        // deterministic regardless of when/where the suite runs.
        Carbon::setTestNow(Carbon::create(2025, 6, 15, 12, 0, 0));

        $this->user = User::factory()->create();
        $this->band = Bands::factory()->create();

        // Member + a second member so the equal-split payout gives a known share.
        DB::table('band_members')->insert([
            ['user_id' => $this->user->id, 'band_id' => $this->band->id, 'created_at' => Carbon::now()->subYears(2), 'updated_at' => Carbon::now()->subYears(2)],
            ['user_id' => User::factory()->create()->id, 'band_id' => $this->band->id, 'created_at' => Carbon::now()->subYears(2), 'updated_at' => Carbon::now()->subYears(2)],
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function seedConfirmedBookingWithVenue(string $venueName, string $venueAddress): Events
    {
        $booking = Bookings::factory()->create([
            'band_id' => $this->band->id,
            'price'   => 2000,
            'status'  => 'confirmed',
        ]);

        return Events::factory()->create([
            'eventable_type' => 'App\\Models\\Bookings',
            'eventable_id'   => $booking->id,
            // Comfortably after the 2-years-ago join date and not in the future,
            // so it counts toward both earnings and locations/travel.
            'date'           => Carbon::now()->subYear(),
            'venue_name'     => $venueName,
            'venue_address'  => $venueAddress,
        ]);
    }

    public function test_endpoint_requires_authentication(): void
    {
        $this->getJson('/api/mobile/me/stats')->assertUnauthorized();
    }

    public function test_endpoint_returns_payment_travel_and_location_blocks(): void
    {
        $this->seedConfirmedBookingWithVenue('The Ruins', '123 Main St, Lafayette, LA 70508, USA');

        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/mobile/me/stats');

        $response->assertOk();
        $response->assertJsonStructure([
            'stats' => [
                'payments' => ['total_earnings', 'booking_count', 'upcoming_earnings', 'upcoming_booking_count', 'by_year', 'by_band', 'bookings_by_year'],
                'travel'   => ['total_miles', 'total_minutes', 'total_hours', 'event_count', 'by_year'],
                'locations',
            ],
        ]);

        // Equal split of a $2000 booking between two members → $1000 share.
        $response->assertJsonPath('stats.payments.booking_count', 1);
        $response->assertJsonPath('stats.payments.total_earnings', '1000.00');
    }

    public function test_future_gigs_are_reported_as_upcoming_not_earned(): void
    {
        // Past gig → earned.
        $this->seedConfirmedBookingWithVenue('The Ruins', '123 Main St, Lafayette, LA 70508, USA');

        // Future gig → upcoming, not counted toward earnings.
        $futureBooking = Bookings::factory()->create([
            'band_id' => $this->band->id,
            'price'   => 4000,
            'status'  => 'confirmed',
        ]);
        Events::factory()->create([
            'eventable_type' => 'App\\Models\\Bookings',
            'eventable_id'   => $futureBooking->id,
            'date'           => Carbon::now()->addMonth(),
        ]);

        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/mobile/me/stats')->assertOk();

        // Earned: only the past $2000 gig, split two ways → $1000.
        $response->assertJsonPath('stats.payments.total_earnings', '1000.00');
        $response->assertJsonPath('stats.payments.booking_count', 1);
        // Upcoming: the future $4000 gig, split two ways → $2000.
        $response->assertJsonPath('stats.payments.upcoming_earnings', '2000.00');
        $response->assertJsonPath('stats.payments.upcoming_booking_count', 1);
    }

    public function test_locations_are_enriched_with_cached_coordinates(): void
    {
        $venueName    = 'Board of Trade Place';
        $venueAddress = 'Board of Trade Pl, New Orleans, LA 70130, USA';
        $fullAddress  = $venueName . ', ' . $venueAddress;

        $this->seedConfirmedBookingWithVenue($venueName, $venueAddress);

        // venue_cache keys on the full_address string (as the web geocoder writes it).
        DB::table('venue_cache')->insert([
            'address'      => $fullAddress,
            'latitude'     => 29.9504042,
            'longitude'    => -90.0673988,
            'usage_count'  => 1,
            'last_used_at' => now(),
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        Sanctum::actingAs($this->user);

        $locations = $this->getJson('/api/mobile/me/stats')
            ->assertOk()
            ->json('stats.locations');

        $match = collect($locations)->firstWhere('full_address', $fullAddress);
        $this->assertNotNull($match);
        $this->assertEquals(29.9504042, $match['lat']);
        $this->assertEquals(-90.0673988, $match['lng']);
    }

    public function test_uncached_locations_have_null_coordinates(): void
    {
        $this->seedConfirmedBookingWithVenue('Unknown Hall', '999 Nowhere Rd, Erath, LA 70533, USA');

        Sanctum::actingAs($this->user);

        $locations = $this->getJson('/api/mobile/me/stats')
            ->assertOk()
            ->json('stats.locations');

        $match = collect($locations)->firstWhere('venue_name', 'Unknown Hall');
        $this->assertNotNull($match);
        $this->assertNull($match['lat']);
        $this->assertNull($match['lng']);
    }
}
