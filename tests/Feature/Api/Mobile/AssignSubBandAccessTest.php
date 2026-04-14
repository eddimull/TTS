<?php

namespace Tests\Feature\Api\Mobile;

use App\Models\BandOwners;
use App\Models\BandSubs;
use App\Models\Bands;
use App\Models\Bookings;
use App\Models\EventMember;
use App\Models\Events;
use App\Models\EventTypes;
use App\Models\RosterMember;
use App\Models\RosterSlot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Verifies that assigning a user as a sub via the mobile assignSub endpoint
 * grants them the band_subs membership they need to access the band's event
 * list via the mobile events index endpoint.
 *
 * Regression test for: sub user not seeing events after being added via assignSub
 * because EventMember rows were not propagated to band_subs.
 */
class AssignSubBandAccessTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private string $ownerToken;
    private Bands $band;
    private Events $event;
    private EventMember $existingMember;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();
        $this->band  = Bands::factory()->create();

        BandOwners::create([
            'user_id' => $this->owner->id,
            'band_id' => $this->band->id,
        ]);

        $this->ownerToken = $this->owner->createToken('test-device')->plainTextToken;

        $booking = Bookings::factory()->create(['band_id' => $this->band->id]);
        $this->event = Events::factory()->create([
            'eventable_id'   => $booking->id,
            'eventable_type' => 'App\\Models\\Bookings',
            'date'           => now()->addDays(7)->format('Y-m-d'),
        ]);

        // An existing EventMember row to update (simulate a slot already on the event)
        $this->existingMember = EventMember::create([
            'event_id' => $this->event->id,
            'band_id'  => $this->band->id,
            'user_id'  => null,
            'name'     => 'Placeholder',
        ]);
    }

    // -------------------------------------------------------------------------
    // assignSub — band_subs side-effect
    // -------------------------------------------------------------------------

    public function test_assign_sub_by_name_does_not_add_to_band_subs(): void
    {
        $this->withToken($this->ownerToken)
            ->postJson("/api/mobile/events/{$this->event->key}/members/{$this->existingMember->id}/sub", [
                'name'  => 'Guest Musician',
                'email' => 'guest@example.com',
            ])
            ->assertOk();

        // No user_id → no band_subs entry expected
        $this->assertDatabaseCount('band_subs', 0);
    }

    public function test_assign_sub_via_roster_member_adds_user_to_band_subs(): void
    {
        $subUser      = User::factory()->create();
        $rosterMember = RosterMember::factory()->user($subUser)->create([
            'roster_id' => \App\Models\Roster::factory()->create(['band_id' => $this->band->id])->id,
        ]);

        $this->assertDatabaseMissing('band_subs', [
            'user_id' => $subUser->id,
            'band_id' => $this->band->id,
        ]);

        $this->withToken($this->ownerToken)
            ->postJson("/api/mobile/events/{$this->event->key}/members/{$this->existingMember->id}/sub", [
                'roster_member_id' => $rosterMember->id,
            ])
            ->assertOk();

        $this->assertDatabaseHas('band_subs', [
            'user_id' => $subUser->id,
            'band_id' => $this->band->id,
        ]);
    }

    // -------------------------------------------------------------------------
    // Events index visibility after assignment
    // -------------------------------------------------------------------------

    public function test_sub_user_cannot_access_events_index_before_assignment(): void
    {
        $subUser  = User::factory()->create();
        $subToken = $subUser->createToken('test-device')->plainTextToken;

        // EnsureUserInBand checks allBands() — sub is not in band_subs yet
        $this->withToken($subToken)
            ->withHeaders(['X-Band-ID' => $this->band->id])
            ->getJson("/api/mobile/bands/{$this->band->id}/events")
            ->assertStatus(403);
    }

    public function test_sub_user_can_access_events_index_after_assignment_via_roster_member(): void
    {
        $subUser      = User::factory()->create();
        $rosterMember = RosterMember::factory()->user($subUser)->create([
            'roster_id' => \App\Models\Roster::factory()->create(['band_id' => $this->band->id])->id,
        ]);
        $subToken = $subUser->createToken('test-device')->plainTextToken;

        // Owner assigns the sub
        $this->withToken($this->ownerToken)
            ->postJson("/api/mobile/events/{$this->event->key}/members/{$this->existingMember->id}/sub", [
                'roster_member_id' => $rosterMember->id,
            ])
            ->assertOk();

        // Sub can now reach the events list for this band
        $this->withToken($subToken)
            ->withHeaders(['X-Band-ID' => $this->band->id])
            ->getJson("/api/mobile/bands/{$this->band->id}/events")
            ->assertOk()
            ->assertJsonStructure(['events']);
    }

    public function test_sub_user_can_access_events_index_after_direct_event_member_creation(): void
    {
        $subUser  = User::factory()->create();
        $subToken = $subUser->createToken('test-device')->plainTextToken;

        // Directly create an EventMember (e.g. via synthetic-slot assignSub path)
        EventMember::create([
            'event_id' => $this->event->id,
            'band_id'  => $this->band->id,
            'user_id'  => $subUser->id,
            'name'     => $subUser->name,
        ]);

        $this->withToken($subToken)
            ->withHeaders(['X-Band-ID' => $this->band->id])
            ->getJson("/api/mobile/bands/{$this->band->id}/events")
            ->assertOk()
            ->assertJsonStructure(['events']);
    }

    // -------------------------------------------------------------------------
    // Idempotency
    // -------------------------------------------------------------------------

    public function test_assigning_same_sub_twice_does_not_create_duplicate_band_subs(): void
    {
        $subUser      = User::factory()->create();
        $rosterMember = RosterMember::factory()->user($subUser)->create([
            'roster_id' => \App\Models\Roster::factory()->create(['band_id' => $this->band->id])->id,
        ]);

        // First assignment
        $this->withToken($this->ownerToken)
            ->postJson("/api/mobile/events/{$this->event->key}/members/{$this->existingMember->id}/sub", [
                'roster_member_id' => $rosterMember->id,
            ])
            ->assertOk();

        $memberId = $this->existingMember->fresh()->id;

        // Clear the sub and reassign to the same person
        $this->withToken($this->ownerToken)
            ->postJson("/api/mobile/events/{$this->event->key}/members/{$memberId}/sub", [
                'clear' => true,
            ])
            ->assertOk();

        $this->withToken($this->ownerToken)
            ->postJson("/api/mobile/events/{$this->event->key}/members/{$memberId}/sub", [
                'roster_member_id' => $rosterMember->id,
            ])
            ->assertOk();

        $this->assertDatabaseCount('band_subs', 1);
    }
}
