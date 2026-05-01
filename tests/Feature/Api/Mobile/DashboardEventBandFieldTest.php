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
}
