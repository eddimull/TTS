<?php

namespace Tests\Feature;

use App\Models\BandOwners;
use App\Models\BandRole;
use App\Models\Bands;
use App\Models\Bookings;
use App\Models\Events;
use App\Models\Roster;
use App\Models\RosterMember;
use App\Models\RosterSlot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class EventMemberStoreTest extends TestCase
{
    use RefreshDatabase;

    private Bands $band;
    private User $owner;
    private Events $event;
    private Roster $roster;
    private BandRole $role;
    private RosterSlot $slot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed', ['--class' => 'SubRolesPermissionsSeeder']);
        \setPermissionsTeamId(0);

        $this->owner = User::factory()->create();
        $this->band = Bands::factory()->create();

        BandOwners::create([
            'user_id' => $this->owner->id,
            'band_id' => $this->band->id,
        ]);

        $booking = Bookings::factory()->create(['band_id' => $this->band->id]);

        $this->role = BandRole::firstOrCreate([
            'band_id' => $this->band->id,
            'name' => 'Guitar',
        ]);

        $this->roster = Roster::factory()->create(['band_id' => $this->band->id]);

        $this->slot = RosterSlot::create([
            'roster_id' => $this->roster->id,
            'band_role_id' => $this->role->id,
            'name' => 'Lead Guitar',
            'quantity' => 1,
            'is_required' => true,
        ]);

        $this->event = Events::factory()->create([
            'eventable_id' => $booking->id,
            'eventable_type' => 'App\Models\Bookings',
            'roster_id' => $this->roster->id,
        ]);
    }

    /**
     * When a sub is added to a slot via the dashboard popup (POST /events/{id}/members),
     * the slot's band_role_id should be saved on the event_member record.
     * Bug: EventMembersController::store only saved name/email/phone — no slot_id or band_role_id.
     */
    public function test_slot_band_role_id_is_used_when_band_role_id_not_sent(): void
    {
        $response = $this->actingAs($this->owner)
            ->postJson("/events/{$this->event->id}/members", [
                'name' => 'Ad Hoc Sub',
                'email' => 'adhoc@example.com',
                'attendance_status' => 'confirmed',
                'slot_id' => $this->slot->id,
                // band_role_id intentionally omitted — simulates dashboard popup behaviour
            ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('event_members', [
            'event_id' => $this->event->id,
            'name' => 'Ad Hoc Sub',
            'slot_id' => $this->slot->id,
            'band_role_id' => $this->role->id,
        ]);
    }

    /**
     * A roster member added to a slot should have the slot's band_role_id saved.
     */
    public function test_roster_member_added_to_slot_inherits_slot_role(): void
    {
        $rosterMember = RosterMember::factory()->create([
            'roster_id' => $this->roster->id,
            'band_role_id' => null,
        ]);

        $response = $this->actingAs($this->owner)
            ->postJson("/events/{$this->event->id}/members", [
                'roster_member_id' => $rosterMember->id,
                'attendance_status' => 'confirmed',
                'slot_id' => $this->slot->id,
                // band_role_id intentionally omitted
            ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('event_members', [
            'event_id' => $this->event->id,
            'roster_member_id' => $rosterMember->id,
            'slot_id' => $this->slot->id,
            'band_role_id' => $this->role->id,
        ]);
    }

    /**
     * When a registered user is added by email without invite_substitute,
     * the event_member record must be linked to their user_id so their
     * name resolves correctly rather than falling back to the name field.
     * Bug: the controller never resolved email → user_id for direct adds.
     */
    public function test_adding_registered_user_by_email_links_user_id(): void
    {
        $existingUser = User::factory()->create([
            'name'  => 'Registered Player',
            'email' => 'registered@example.com',
        ]);

        $response = $this->actingAs($this->owner)
            ->postJson("/events/{$this->event->id}/members", [
                'name'              => 'Registered Player',
                'email'             => 'registered@example.com',
                'attendance_status' => 'confirmed',
                'slot_id'           => $this->slot->id,
                // invite_substitute intentionally omitted (false by default)
            ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('event_members', [
            'event_id' => $this->event->id,
            'user_id'  => $existingUser->id,
            'email'    => 'registered@example.com',
        ]);
    }

    /**
     * Re-adding a registered user who was previously soft-deleted from the event
     * must restore the record rather than crash with a unique-key violation.
     * This mirrors the restore logic already in EventMemberController (singular).
     */
    public function test_readding_soft_deleted_user_restores_record(): void
    {
        $existingUser = User::factory()->create([
            'name'  => 'Returning Member',
            'email' => 'returning@example.com',
        ]);

        // Simulate a previously removed event member (soft-deleted)
        $member = \App\Models\EventMember::create([
            'event_id' => $this->event->id,
            'band_id'  => $this->band->id,
            'user_id'  => $existingUser->id,
            'email'    => 'returning@example.com',
        ]);
        $member->delete();

        $response = $this->actingAs($this->owner)
            ->postJson("/events/{$this->event->id}/members", [
                'name'              => 'Returning Member',
                'email'             => 'returning@example.com',
                'attendance_status' => 'confirmed',
                'slot_id'           => $this->slot->id,
            ]);

        $response->assertStatus(201);

        // Only one event_member for this user/event (the restored one)
        $this->assertDatabaseCount('event_members', 1);
        $this->assertDatabaseHas('event_members', [
            'event_id'   => $this->event->id,
            'user_id'    => $existingUser->id,
            'slot_id'    => $this->slot->id,
            'deleted_at' => null,
        ]);
    }

    /**
     * Sending invite_substitute without an email must be rejected with 422 and
     * must not create an event_subs record or send mail.
     * Bug: a call-list entry with null custom_email sent invite_substitute=true
     * with no email, creating a dangling event_subs record with a null email.
     */
    public function test_invite_substitute_without_email_is_rejected(): void
    {
        Mail::fake();

        $response = $this->actingAs($this->owner)
            ->postJson("/events/{$this->event->id}/members", [
                'name' => 'No Email Sub',
                'invite_substitute' => true,
                // email intentionally omitted
            ]);

        $response->assertStatus(422);

        $this->assertDatabaseMissing('event_subs', [
            'event_id' => $this->event->id,
        ]);

        Mail::assertNothingSent();
    }
}
