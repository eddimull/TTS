<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Bands;
use App\Models\User;
use App\Models\Events;
use App\Models\Bookings;
use App\Models\EventSubs;
use App\Models\BandOwners;
use App\Models\BandMembers;
use App\Models\BandSubs;
use App\Services\SubInvitationService;

class SubNeverBecomesBandMemberTest extends TestCase
{
    use RefreshDatabase;

    protected $band;
    protected $owner;

    protected function setUp(): void
    {
        parent::setUp();

        // Create sub role and permissions
        $this->artisan('db:seed', ['--class' => 'SubRolesPermissionsSeeder']);

        // Create test data
        $this->band = Bands::factory()->create(['name' => 'Test Band']);
        $this->owner = User::factory()->create();

        BandOwners::create([
            'user_id' => $this->owner->id,
            'band_id' => $this->band->id
        ]);
    }

    public function test_sub_invitation_service_never_adds_to_band_owners()
    {
        $booking = Bookings::factory()->create(['band_id' => $this->band->id]);
        $event = Events::factory()->create([
            'eventable_id' => $booking->id,
            'eventable_type' => 'App\Models\Bookings',
            'date' => now()->addDays(7),
        ]);

        $subInvitationService = new SubInvitationService();

        // Invite a new sub
        $subInvitationService->inviteSubToEvent(
            eventId: $event->id,
            bandId: $this->band->id,
            email: 'newsub@example.com',
            name: 'New Sub',
        );

        // Verify NO band_owners record was created for this email
        // The user won't exist yet (invitation only), so just verify
        // no band_owners records exist for band with email matching pattern
        $bandOwnerEmails = \DB::table('band_owners')
            ->join('users', 'band_owners.user_id', '=', 'users.id')
            ->where('band_owners.band_id', $this->band->id)
            ->where('users.email', 'newsub@example.com')
            ->count();

        $this->assertEquals(0, $bandOwnerEmails,
            'Sub invitation should not create band_owners record');
    }

    public function test_sub_invitation_service_never_adds_to_band_members()
    {
        $booking = Bookings::factory()->create(['band_id' => $this->band->id]);
        $event = Events::factory()->create([
            'eventable_id' => $booking->id,
            'eventable_type' => 'App\Models\Bookings',
            'date' => now()->addDays(7),
        ]);

        $subInvitationService = new SubInvitationService();

        // Invite a new sub
        $subInvitationService->inviteSubToEvent(
            eventId: $event->id,
            bandId: $this->band->id,
            email: 'newsub@example.com',
            name: 'New Sub',
        );

        // Register the user
        $this->post('/register', [
            'name' => 'New Sub',
            'email' => 'newsub@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $user = User::where('email', 'newsub@example.com')->first();

        // Verify they're in band_subs but NOT band_members or band_owners
        $this->assertDatabaseHas('band_subs', [
            'user_id' => $user->id,
            'band_id' => $this->band->id,
        ]);

        $this->assertDatabaseMissing('band_members', [
            'user_id' => $user->id,
            'band_id' => $this->band->id,
        ]);

        $this->assertDatabaseMissing('band_owners', [
            'user_id' => $user->id,
            'band_id' => $this->band->id,
        ]);
    }

    public function test_existing_user_invited_as_sub_never_gets_band_member_access()
    {
        // Create an existing user (not related to any band)
        $existingUser = User::factory()->create([
            'email' => 'existinguser@example.com',
            'name' => 'Existing User',
        ]);

        $booking = Bookings::factory()->create(['band_id' => $this->band->id]);
        $event = Events::factory()->create([
            'eventable_id' => $booking->id,
            'eventable_type' => 'App\Models\Bookings',
            'date' => now()->addDays(7),
        ]);

        $subInvitationService = new SubInvitationService();

        // Invite existing user as sub
        $subInvitationService->inviteSubToEvent(
            eventId: $event->id,
            bandId: $this->band->id,
            email: $existingUser->email,
            name: $existingUser->name,
        );

        // Verify they're in band_subs but NOT band_members or band_owners
        $this->assertDatabaseHas('band_subs', [
            'user_id' => $existingUser->id,
            'band_id' => $this->band->id,
        ]);

        $this->assertDatabaseMissing('band_members', [
            'user_id' => $existingUser->id,
            'band_id' => $this->band->id,
        ]);

        $this->assertDatabaseMissing('band_owners', [
            'user_id' => $existingUser->id,
            'band_id' => $this->band->id,
        ]);
    }

    public function test_sub_with_band_owner_role_sees_all_events()
    {
        // This test documents the expected behavior:
        // If someone is BOTH a sub AND a band owner, they see all events

        $subUser = User::factory()->create([
            'email' => 'subowner@example.com',
        ]);

        // Make them a band owner
        BandOwners::create([
            'user_id' => $subUser->id,
            'band_id' => $this->band->id,
        ]);

        // Also make them a sub (unusual but possible)
        $subUser->assignRole('sub');
        BandSubs::create([
            'user_id' => $subUser->id,
            'band_id' => $this->band->id,
        ]);

        // Create 2 events, invite sub to only 1
        $booking1 = Bookings::factory()->create(['band_id' => $this->band->id]);
        $event1 = Events::factory()->create([
            'eventable_id' => $booking1->id,
            'eventable_type' => 'App\Models\Bookings',
            'date' => now()->addDays(7),
        ]);

        $booking2 = Bookings::factory()->create(['band_id' => $this->band->id]);
        $event2 = Events::factory()->create([
            'eventable_id' => $booking2->id,
            'eventable_type' => 'App\Models\Bookings',
            'date' => now()->addDays(14),
        ]);

        EventSubs::create([
            'event_id' => $event1->id,
            'band_id' => $this->band->id,
            'user_id' => $subUser->id,
            'email' => $subUser->email,
            'pending' => false,
            'accepted_at' => now(),
        ]);

        $this->actingAs($subUser);

        $userEventsService = new \App\Services\UserEventsService();
        $events = $userEventsService->getEvents();

        // Since they're a band owner, they should see ALL events, not just invited ones
        $this->assertGreaterThanOrEqual(2, $events->count(),
            'Band owner should see all events, even if they also have sub role');
    }
}
