<?php

namespace Tests\Feature;

use App\Models\BandSubs;
use App\Models\Bands;
use App\Models\Bookings;
use App\Models\EventMember;
use App\Models\Events;
use App\Models\RosterMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Verifies the EventMember booted() hook that ensures a sub user is always
 * present in band_subs whenever they are assigned to an EventMember row.
 *
 * This covers the bug where a user assigned as a sub via the mobile
 * assignSub endpoint would not appear in band_subs, causing canRead()
 * to deny them access to the band's event list.
 */
class EventMemberBandSubTest extends TestCase
{
    use RefreshDatabase;

    private Bands $band;
    private Events $event;

    protected function setUp(): void
    {
        parent::setUp();

        $this->band = Bands::factory()->create();
        $booking = Bookings::factory()->create(['band_id' => $this->band->id]);
        $this->event = Events::factory()->create([
            'eventable_id'   => $booking->id,
            'eventable_type' => 'App\\Models\\Bookings',
        ]);
    }

    public function test_creating_event_member_with_user_id_adds_band_sub(): void
    {
        $user = User::factory()->create();

        $this->assertDatabaseMissing('band_subs', [
            'user_id' => $user->id,
            'band_id' => $this->band->id,
        ]);

        EventMember::factory()->create([
            'event_id'         => $this->event->id,
            'band_id'          => $this->band->id,
            'user_id'          => $user->id,
            'roster_member_id' => null,
        ]);

        $this->assertDatabaseHas('band_subs', [
            'user_id' => $user->id,
            'band_id' => $this->band->id,
        ]);
    }

    public function test_updating_event_member_to_set_user_id_adds_band_sub(): void
    {
        $user = User::factory()->create();

        // Start with no user_id (unfilled slot)
        $member = EventMember::factory()->create([
            'event_id'         => $this->event->id,
            'band_id'          => $this->band->id,
            'user_id'          => null,
            'roster_member_id' => null,
            'name'             => 'Placeholder',
        ]);

        $this->assertDatabaseMissing('band_subs', [
            'user_id' => $user->id,
            'band_id' => $this->band->id,
        ]);

        $member->update(['user_id' => $user->id]);

        $this->assertDatabaseHas('band_subs', [
            'user_id' => $user->id,
            'band_id' => $this->band->id,
        ]);
    }

    public function test_creating_event_member_without_user_id_does_not_add_band_sub(): void
    {
        $initialCount = BandSubs::count();

        EventMember::factory()->create([
            'event_id'         => $this->event->id,
            'band_id'          => $this->band->id,
            'user_id'          => null,
            'roster_member_id' => null,
            'name'             => 'Custom Sub Name',
        ]);

        $this->assertEquals($initialCount, BandSubs::count());
    }

    public function test_band_sub_is_not_duplicated_when_user_already_present(): void
    {
        $user = User::factory()->create();

        BandSubs::create(['user_id' => $user->id, 'band_id' => $this->band->id]);

        EventMember::factory()->create([
            'event_id'         => $this->event->id,
            'band_id'          => $this->band->id,
            'user_id'          => $user->id,
            'roster_member_id' => null,
        ]);

        $this->assertDatabaseCount('band_subs', 1);
    }

    public function test_is_sub_of_band_returns_true_after_event_member_created(): void
    {
        $user = User::factory()->create();

        $this->assertFalse($user->isSubOfBand($this->band->id));

        EventMember::factory()->create([
            'event_id'         => $this->event->id,
            'band_id'          => $this->band->id,
            'user_id'          => $user->id,
            'roster_member_id' => null,
        ]);

        $this->assertTrue($user->fresh()->isSubOfBand($this->band->id));
    }

    public function test_can_read_events_returns_true_after_event_member_created(): void
    {
        $user = User::factory()->create();

        $this->assertFalse($user->canRead('events', $this->band->id));

        EventMember::factory()->create([
            'event_id'         => $this->event->id,
            'band_id'          => $this->band->id,
            'user_id'          => $user->id,
            'roster_member_id' => null,
        ]);

        $this->assertTrue($user->fresh()->canRead('events', $this->band->id));
    }
}
