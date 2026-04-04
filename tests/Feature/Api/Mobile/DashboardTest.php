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

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_requires_authentication(): void
    {
        $this->getJson('/api/mobile/dashboard')->assertUnauthorized();
    }

    public function test_dashboard_returns_events_for_authenticated_user(): void
    {
        $user = User::factory()->create();
        $band = Bands::factory()->create();
        $band->owners()->create(['user_id' => $user->id]);

        $eventType = EventTypes::factory()->create();
        $booking = Bookings::factory()->create(['band_id' => $band->id]);
        Events::factory()->create([
            'eventable_id'   => $booking->id,
            'eventable_type' => 'App\\Models\\Bookings',
            'event_type_id'  => $eventType->id,
            'date'           => now()->addDays(7)->format('Y-m-d'),
        ]);

        $token = $user->createToken('test-device')->plainTextToken;

        $response = $this->withToken($token)->getJson('/api/mobile/dashboard');

        $response->assertOk()
            ->assertJsonStructure([
                'events',
                'upcoming_charts',
            ]);

        $this->assertIsArray($response->json('events'));
        $this->assertIsArray($response->json('upcoming_charts'));
    }

    public function test_dashboard_returns_empty_arrays_for_user_with_no_bands(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-device')->plainTextToken;

        $response = $this->withToken($token)->getJson('/api/mobile/dashboard');

        $response->assertOk();
        $this->assertEmpty($response->json('events'));
        $this->assertEmpty($response->json('upcoming_charts'));
    }

    public function test_dashboard_events_are_sorted_by_date_ascending(): void
    {
        $user = User::factory()->create();
        $band = Bands::factory()->create();
        $band->owners()->create(['user_id' => $user->id]);
        $eventType = EventTypes::factory()->create();

        $later = Bookings::factory()->create(['band_id' => $band->id]);
        Events::factory()->create([
            'eventable_id'   => $later->id,
            'eventable_type' => 'App\\Models\\Bookings',
            'event_type_id'  => $eventType->id,
            'date'           => now()->addDays(14)->format('Y-m-d'),
        ]);

        $sooner = Bookings::factory()->create(['band_id' => $band->id]);
        Events::factory()->create([
            'eventable_id'   => $sooner->id,
            'eventable_type' => 'App\\Models\\Bookings',
            'event_type_id'  => $eventType->id,
            'date'           => now()->addDays(3)->format('Y-m-d'),
        ]);

        $token = $user->createToken('test-device')->plainTextToken;
        $response = $this->withToken($token)->getJson('/api/mobile/dashboard');

        $response->assertOk();
        $dates = collect($response->json('events'))->pluck('date')->toArray();
        $sorted = $dates;
        sort($sorted);
        $this->assertEquals($sorted, $dates, 'events should be sorted by date ascending');
    }
}
