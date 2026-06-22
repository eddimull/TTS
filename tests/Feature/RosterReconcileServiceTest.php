<?php

namespace Tests\Feature;

use App\Models\Bands;
use App\Models\Bookings;
use App\Models\EventMember;
use App\Models\Events;
use App\Models\Roster;
use App\Models\RosterMember;
use App\Models\User;
use App\Services\RosterReconcileService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RosterReconcileServiceTest extends TestCase
{
    use RefreshDatabase;

    protected Bands $band;
    protected Roster $roster;
    protected RosterReconcileService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->band = Bands::factory()->create();
        $this->roster = Roster::factory()->create(['band_id' => $this->band->id]);
        $this->service = app(RosterReconcileService::class);
    }

    /**
     * Create an event for this band using a given roster and date.
     *
     * Note: setting roster_id triggers Events::syncRosterMembers() via the
     * model observer, which seeds the event with whichever roster members are
     * active AT THIS MOMENT. Create members after the event to model someone
     * being added to the roster later.
     */
    private function makeEvent(?Roster $roster, string $date): Events
    {
        $booking = Bookings::factory()->create(['band_id' => $this->band->id]);

        return Events::factory()->create([
            'eventable_id' => $booking->id,
            'eventable_type' => Bookings::class,
            'roster_id' => $roster?->id,
            'date' => $date,
        ]);
    }

    private function futureDate(): string
    {
        return now()->addWeek()->toDateString();
    }

    private function pastDate(): string
    {
        return now()->subWeek()->toDateString();
    }

    #[Test]
    public function add_member_populates_only_future_events_using_this_roster()
    {
        // Events created first => member is genuinely absent from them.
        $futureEvent = $this->makeEvent($this->roster, $this->futureDate());
        $pastEvent = $this->makeEvent($this->roster, $this->pastDate());
        $otherRoster = Roster::factory()->create(['band_id' => $this->band->id]);
        $otherRosterEvent = $this->makeEvent($otherRoster, $this->futureDate());

        $user = User::factory()->create();
        $member = RosterMember::factory()->user($user)->create(['roster_id' => $this->roster->id]);

        $count = $this->service->addMemberToFutureEvents($member);

        $this->assertEquals(1, $count);
        $this->assertDatabaseHas('event_members', [
            'event_id' => $futureEvent->id,
            'roster_member_id' => $member->id,
            'attendance_status' => 'confirmed',
        ]);
        $this->assertDatabaseMissing('event_members', [
            'event_id' => $pastEvent->id,
            'roster_member_id' => $member->id,
        ]);
        $this->assertDatabaseMissing('event_members', [
            'event_id' => $otherRosterEvent->id,
            'roster_member_id' => $member->id,
        ]);
    }

    #[Test]
    public function add_member_includes_same_day_events()
    {
        // events.date is a DATE column; an event happening today must still
        // count as "future" even when the clock is past midnight.
        $todayEvent = $this->makeEvent($this->roster, now()->toDateString());
        $member = RosterMember::factory()->user()->create(['roster_id' => $this->roster->id]);

        $count = $this->service->addMemberToFutureEvents($member);

        $this->assertEquals(1, $count);
        $this->assertDatabaseHas('event_members', [
            'event_id' => $todayEvent->id,
            'roster_member_id' => $member->id,
        ]);
    }

    #[Test]
    public function add_member_is_idempotent_when_already_present()
    {
        $event = $this->makeEvent($this->roster, $this->futureDate());
        $member = RosterMember::factory()->user()->create(['roster_id' => $this->roster->id]);

        $first = $this->service->addMemberToFutureEvents($member);
        $second = $this->service->addMemberToFutureEvents($member);

        $this->assertEquals(1, $first);
        $this->assertEquals(0, $second);
        $this->assertEquals(1, EventMember::where('event_id', $event->id)
            ->where('roster_member_id', $member->id)
            ->count());
    }

    #[Test]
    public function add_member_is_noop_when_member_already_synced_to_event()
    {
        // Member exists before the event => observer already seeds them.
        $member = RosterMember::factory()->user()->create(['roster_id' => $this->roster->id]);
        $event = $this->makeEvent($this->roster, $this->futureDate());

        $count = $this->service->addMemberToFutureEvents($member);

        $this->assertEquals(0, $count);
        $this->assertEquals(1, EventMember::where('event_id', $event->id)
            ->where('roster_member_id', $member->id)
            ->count());
    }

    #[Test]
    public function remove_member_clears_future_events_only()
    {
        $user = User::factory()->create();
        $member = RosterMember::factory()->user($user)->create(['roster_id' => $this->roster->id]);

        // Member exists, so both events get them via the observer on create.
        $futureEvent = $this->makeEvent($this->roster, $this->futureDate());
        $pastEvent = $this->makeEvent($this->roster, $this->pastDate());

        $this->assertDatabaseHas('event_members', [
            'event_id' => $futureEvent->id,
            'roster_member_id' => $member->id,
        ]);

        $count = $this->service->removeMemberFromFutureEvents($member);

        $this->assertEquals(1, $count);
        $this->assertDatabaseMissing('event_members', [
            'event_id' => $futureEvent->id,
            'roster_member_id' => $member->id,
        ]);
        // Past event membership preserved.
        $this->assertDatabaseHas('event_members', [
            'event_id' => $pastEvent->id,
            'roster_member_id' => $member->id,
        ]);
    }

    #[Test]
    public function re_add_after_remove_restores_without_unique_constraint_error()
    {
        $event = $this->makeEvent($this->roster, $this->futureDate());
        $member = RosterMember::factory()->user()->create(['roster_id' => $this->roster->id]);

        $this->service->addMemberToFutureEvents($member);
        $this->service->removeMemberFromFutureEvents($member);

        // Force-deleted, so re-add inserts cleanly (no unique collision).
        $count = $this->service->addMemberToFutureEvents($member);

        $this->assertEquals(1, $count);
        $this->assertEquals(1, EventMember::where('event_id', $event->id)
            ->where('roster_member_id', $member->id)
            ->count());
    }

    #[Test]
    public function add_and_remove_handle_non_user_roster_members()
    {
        $event = $this->makeEvent($this->roster, $this->futureDate());
        $member = RosterMember::factory()->nonUser()->create(['roster_id' => $this->roster->id]);

        $added = $this->service->addMemberToFutureEvents($member);
        $this->assertEquals(1, $added);
        $this->assertDatabaseHas('event_members', [
            'event_id' => $event->id,
            'roster_member_id' => $member->id,
            'user_id' => null,
        ]);

        $removed = $this->service->removeMemberFromFutureEvents($member);
        $this->assertEquals(1, $removed);
        $this->assertDatabaseMissing('event_members', [
            'event_id' => $event->id,
            'roster_member_id' => $member->id,
        ]);
    }

    #[Test]
    public function diff_classifies_extra_and_missing()
    {
        // Active member added after the event => absent => missing.
        $event = $this->makeEvent($this->roster, $this->futureDate());
        $active = RosterMember::factory()->user()->create(['roster_id' => $this->roster->id]);

        // Someone who quit but is still stamped on the future event => extra.
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

        $diff = $this->service->diffFutureEvents($this->roster);

        $this->assertCount(1, $diff['extra']);
        $this->assertEquals($quit->id, $diff['extra'][0]['roster_member_id']);
        $this->assertEquals(1, $diff['extra'][0]['event_count']);

        $this->assertCount(1, $diff['missing']);
        $this->assertEquals($active->id, $diff['missing'][0]['roster_member_id']);
        $this->assertEquals(1, $diff['missing'][0]['event_count']);
    }

    #[Test]
    public function apply_reconcile_applies_only_selected_actions()
    {
        $event = $this->makeEvent($this->roster, $this->futureDate());

        // Active member absent from the event (created after) => addable.
        $toAdd = RosterMember::factory()->user()->create(['roster_id' => $this->roster->id]);

        // Inactive member still stamped on the event => removable.
        $toRemove = RosterMember::factory()->user()->create([
            'roster_id' => $this->roster->id,
            'is_active' => false,
        ]);
        EventMember::create([
            'event_id' => $event->id,
            'band_id' => $this->band->id,
            'roster_member_id' => $toRemove->id,
            'user_id' => $toRemove->user_id,
            'attendance_status' => 'confirmed',
        ]);

        $result = $this->service->applyReconcile(
            $this->roster,
            [$toRemove->id],
            [$toAdd->id],
        );

        $this->assertEquals(1, $result['removed']);
        $this->assertEquals(1, $result['added']);
        $this->assertDatabaseMissing('event_members', [
            'event_id' => $event->id,
            'roster_member_id' => $toRemove->id,
        ]);
        $this->assertDatabaseHas('event_members', [
            'event_id' => $event->id,
            'roster_member_id' => $toAdd->id,
        ]);
    }
}
