<?php

namespace Tests\Feature;

use App\Models\Bands;
use App\Models\BandOwners;
use App\Models\Bookings;
use App\Models\EventMember;
use App\Models\Events;
use App\Models\Roster;
use App\Models\RosterMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RosterFutureEventSyncEndpointsTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;
    protected Bands $band;
    protected Roster $roster;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();
        $this->band = Bands::factory()->create();
        BandOwners::create(['band_id' => $this->band->id, 'user_id' => $this->owner->id]);
        $this->roster = Roster::factory()->create(['band_id' => $this->band->id]);
    }

    private function ownerHeaders(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->owner->createToken('test-device')->plainTextToken,
            'X-Band-ID' => $this->band->id,
            'Accept' => 'application/json',
        ];
    }

    private function futureEvent(?Roster $roster = null): Events
    {
        $booking = Bookings::factory()->create(['band_id' => $this->band->id]);

        return Events::factory()->create([
            'eventable_id' => $booking->id,
            'eventable_type' => Bookings::class,
            'roster_id' => ($roster ?? $this->roster)->id,
            'date' => now()->addWeek()->toDateString(),
        ]);
    }

    #[Test]
    public function web_store_with_flag_adds_member_to_future_events()
    {
        $event = $this->futureEvent();
        $user = User::factory()->create();

        $response = $this->actingAs($this->owner)
            ->postJson("/rosters/{$this->roster->id}/members", [
                'user_id' => $user->id,
                'apply_to_future_events' => true,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('future_events_affected', 1);

        $this->assertDatabaseHas('event_members', [
            'event_id' => $event->id,
            'user_id' => $user->id,
        ]);
    }

    #[Test]
    public function web_store_without_flag_does_not_touch_future_events()
    {
        $event = $this->futureEvent();
        $user = User::factory()->create();

        $this->actingAs($this->owner)
            ->postJson("/rosters/{$this->roster->id}/members", [
                'user_id' => $user->id,
            ])
            ->assertStatus(201)
            ->assertJsonPath('future_events_affected', 0);

        $this->assertDatabaseMissing('event_members', [
            'event_id' => $event->id,
            'user_id' => $user->id,
        ]);
    }

    #[Test]
    public function web_destroy_with_flag_removes_member_from_future_events()
    {
        $user = User::factory()->create();
        $member = RosterMember::factory()->user($user)->create(['roster_id' => $this->roster->id]);
        // Member exists, so the event observer seeds them on create.
        $event = $this->futureEvent();

        $this->assertDatabaseHas('event_members', [
            'event_id' => $event->id,
            'roster_member_id' => $member->id,
        ]);

        $this->actingAs($this->owner)
            ->deleteJson("/roster-members/{$member->id}", [
                'apply_to_future_events' => true,
            ])
            ->assertStatus(200)
            ->assertJsonPath('future_events_affected', 1);

        $this->assertDatabaseMissing('event_members', [
            'event_id' => $event->id,
            'roster_member_id' => $member->id,
        ]);
    }

    #[Test]
    public function web_diff_and_reconcile_endpoints_work()
    {
        $event = $this->futureEvent();
        // Active member added after the event => missing from it.
        $active = RosterMember::factory()->user()->create(['roster_id' => $this->roster->id]);
        // Inactive member stamped on the event => extra.
        $quit = RosterMember::factory()->user()->create([
            'roster_id' => $this->roster->id,
            'is_active' => false,
        ]);
        EventMember::create([
            'event_id' => $event->id,
            'band_id' => $this->band->id,
            'roster_member_id' => $quit->id,
            'user_id' => $quit->user_id,
            'attendance_status' => 'confirmed',
        ]);

        $this->actingAs($this->owner)
            ->getJson("/rosters/{$this->roster->id}/future-events-diff")
            ->assertStatus(200)
            ->assertJsonPath('extra.0.roster_member_id', $quit->id)
            ->assertJsonPath('missing.0.roster_member_id', $active->id);

        $this->actingAs($this->owner)
            ->postJson("/rosters/{$this->roster->id}/reconcile-future-events", [
                'remove_member_ids' => [$quit->id],
                'add_member_ids' => [$active->id],
            ])
            ->assertStatus(200)
            ->assertJsonPath('removed', 1)
            ->assertJsonPath('added', 1);

        $this->assertDatabaseMissing('event_members', [
            'event_id' => $event->id,
            'roster_member_id' => $quit->id,
        ]);
        $this->assertDatabaseHas('event_members', [
            'event_id' => $event->id,
            'roster_member_id' => $active->id,
        ]);
    }

    #[Test]
    public function mobile_store_with_flag_adds_member_to_future_events()
    {
        $event = $this->futureEvent();
        $user = User::factory()->create();

        $this->withHeaders($this->ownerHeaders())
            ->postJson("/api/mobile/bands/{$this->band->id}/rosters/{$this->roster->id}/members", [
                'user_id' => $user->id,
                'apply_to_future_events' => true,
            ])
            ->assertStatus(201)
            ->assertJsonPath('future_events_affected', 1);

        $this->assertDatabaseHas('event_members', [
            'event_id' => $event->id,
            'user_id' => $user->id,
        ]);
    }

    #[Test]
    public function mobile_destroy_with_flag_removes_member_from_future_events()
    {
        $user = User::factory()->create();
        $member = RosterMember::factory()->user($user)->create(['roster_id' => $this->roster->id]);
        $event = $this->futureEvent();

        $this->withHeaders($this->ownerHeaders())
            ->deleteJson("/api/mobile/bands/{$this->band->id}/roster-members/{$member->id}", [
                'apply_to_future_events' => true,
            ])
            ->assertStatus(200)
            ->assertJsonPath('future_events_affected', 1);

        $this->assertDatabaseMissing('event_members', [
            'event_id' => $event->id,
            'roster_member_id' => $member->id,
        ]);
    }

    #[Test]
    public function mobile_reconcile_endpoint_works()
    {
        $event = $this->futureEvent();
        $active = RosterMember::factory()->user()->create(['roster_id' => $this->roster->id]);

        $this->withHeaders($this->ownerHeaders())
            ->postJson("/api/mobile/bands/{$this->band->id}/rosters/{$this->roster->id}/reconcile-future-events", [
                'add_member_ids' => [$active->id],
            ])
            ->assertStatus(200)
            ->assertJsonPath('added', 1);

        $this->assertDatabaseHas('event_members', [
            'event_id' => $event->id,
            'roster_member_id' => $active->id,
        ]);
    }
}
