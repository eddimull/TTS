<?php

namespace Tests\Feature\Api\Mobile;

use App\Models\BandOwners;
use App\Models\Bands;
use App\Models\Bookings;
use App\Models\EventMember;
use App\Models\Events;
use App\Models\Roster;
use App\Models\RosterSlot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Assigning a custom sub by name/email through the mobile assignSub endpoint
 * must link the EventMember row to the sub's account when one exists —
 * calendar visibility for sub-only users keys on event_members.user_id
 * (UserEventsService::getSubEvents), so an unlinked row means the gig never
 * shows on the sub's calendar.
 *
 * Regression test for: re-adding a sub to an event after they registered
 * still left event_members.user_id NULL.
 */
class AssignSubResolvesRegisteredUserTest extends TestCase
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

        $this->artisan('db:seed', ['--class' => 'SubRolesPermissionsSeeder']);
        \setPermissionsTeamId(0);

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

        $this->existingMember = EventMember::create([
            'event_id' => $this->event->id,
            'band_id'  => $this->band->id,
            'user_id'  => null,
            'name'     => 'Placeholder',
        ]);
    }

    private function assignSub(int $memberId, array $body)
    {
        return $this->withToken($this->ownerToken)
            ->postJson("/api/mobile/events/{$this->event->key}/members/{$memberId}/sub", $body);
    }

    public function test_update_path_links_registered_user_by_email(): void
    {
        $subUser = User::factory()->create(['email' => 'registered.sub@example.com']);

        $this->assignSub($this->existingMember->id, [
            'name'  => 'Registered Sub',
            'email' => 'registered.sub@example.com',
        ])->assertOk();

        $this->assertEquals($subUser->id, $this->existingMember->fresh()->user_id);
        $this->assertDatabaseHas('band_subs', [
            'user_id' => $subUser->id,
            'band_id' => $this->band->id,
        ]);
    }

    public function test_create_path_links_registered_user_by_email(): void
    {
        $subUser = User::factory()->create(['email' => 'slot.sub@example.com']);

        $roster = Roster::factory()->create(['band_id' => $this->band->id]);
        $slot   = RosterSlot::create([
            'roster_id' => $roster->id,
            'name'      => 'Guitar',
            'quantity'  => 1,
        ]);

        $this->assignSub(0, [
            'slot_id' => $slot->id,
            'name'    => 'Slot Sub',
            'email'   => 'slot.sub@example.com',
        ])->assertOk();

        $this->assertDatabaseHas('event_members', [
            'event_id' => $this->event->id,
            'slot_id'  => $slot->id,
            'user_id'  => $subUser->id,
        ]);
    }

    public function test_linked_sub_sees_event_on_mobile_events_index(): void
    {
        $subUser = User::factory()->create(['email' => 'visible.sub@example.com']);

        $this->assignSub($this->existingMember->id, [
            'name'  => 'Visible Sub',
            'email' => 'visible.sub@example.com',
        ])->assertOk();

        $subToken = $subUser->createToken('sub-device')->plainTextToken;

        $response = $this->withToken($subToken)
            ->withHeaders(['X-Band-ID' => $this->band->id])
            ->getJson("/api/mobile/bands/{$this->band->id}/events")
            ->assertOk();

        $this->assertContains(
            $this->event->id,
            collect($response->json('events'))->pluck('id')->all(),
            'Sub should see the event they were assigned to on the mobile events index'
        );
    }

    public function test_unregistered_email_leaves_user_id_null(): void
    {
        $this->assignSub($this->existingMember->id, [
            'name'  => 'Stranger',
            'email' => 'nobody@example.com',
        ])->assertOk();

        $this->assertNull($this->existingMember->fresh()->user_id);
    }

    public function test_conflicting_row_on_same_event_leaves_user_id_null(): void
    {
        $subUser = User::factory()->create(['email' => 'dupe.sub@example.com']);

        // The unique index on (event_id, user_id) still contains soft-deleted
        // rows — resolving the user here would blow up on restore/insert.
        EventMember::create([
            'event_id' => $this->event->id,
            'band_id'  => $this->band->id,
            'user_id'  => $subUser->id,
            'name'     => 'Old row',
        ])->delete();

        $this->assignSub($this->existingMember->id, [
            'name'  => 'Dupe Sub',
            'email' => 'dupe.sub@example.com',
        ])->assertOk();

        $this->assertNull($this->existingMember->fresh()->user_id);
    }
}
