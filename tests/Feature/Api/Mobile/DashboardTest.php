<?php

namespace Tests\Feature\Api\Mobile;

use App\Models\BandOwners;
use App\Models\Bands;
use App\Models\BandSubs;
use App\Models\Bookings;
use App\Models\EventMember;
use App\Models\Events;
use App\Models\EventTypes;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
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

    /**
     * Regression for production user 40 (Wesley): a sub-only user saw their
     * events on web but got an EMPTY dashboard on mobile.
     *
     * Cause: UserEventsService::getEvents() branches on $user->hasRole('sub'),
     * which Spatie evaluates against the CURRENT permissions team. The mobile
     * DashboardController only calls Auth::setUser() and never sets the team,
     * so hasRole('sub') returned false, the sub branch was skipped, and the
     * user (with no band ownership/membership) fell through to zero events.
     */
    public function test_dashboard_returns_assigned_events_for_sub_only_user(): void
    {
        // `sub` role is global; assignments live at team 0.
        setPermissionsTeamId(0);
        Role::firstOrCreate(['name' => 'sub', 'guard_name' => 'web']);

        $band = Bands::factory()->create();

        $sub = User::factory()->create();
        $sub->assignRole('sub');
        BandSubs::firstOrCreate(['user_id' => $sub->id, 'band_id' => $band->id]);

        $eventType = EventTypes::factory()->create();
        $booking = Bookings::factory()->create(['band_id' => $band->id]);
        $assigned = Events::factory()->create([
            'eventable_id'   => $booking->id,
            'eventable_type' => 'App\\Models\\Bookings',
            'event_type_id'  => $eventType->id,
            'date'           => now()->addDays(7)->format('Y-m-d'),
        ]);

        // Assign the sub to the event (roster_member_id NULL == sub, like prod).
        EventMember::create([
            'event_id'         => $assigned->id,
            'band_id'          => $band->id,
            'user_id'          => $sub->id,
            'roster_member_id' => null,
            'name'             => $sub->name,
        ]);

        $token = $sub->createToken('test-device')->plainTextToken;

        // Simulate a fresh request: no permissions team is set, exactly as the
        // mobile DashboardController leaves it. Without the fix this yields 0
        // events because hasRole('sub') resolves false here.
        setPermissionsTeamId(0);
        app(\Spatie\Permission\PermissionRegistrar::class)->setPermissionsTeamId(null);

        $response = $this->withToken($token)->getJson('/api/mobile/dashboard');

        $response->assertOk();

        $ids = collect($response->json('events'))->pluck('id');
        $this->assertTrue(
            $ids->contains($assigned->id),
            'sub-only user must see their assigned event on the mobile dashboard',
        );
    }
}
