<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Bands;
use App\Models\User;
use App\Models\Events;
use App\Models\Bookings;
use App\Models\EventSubs;
use App\Models\BandSubs;
use App\Models\BandOwners;
use App\Services\SubInvitationService;
use Illuminate\Support\Facades\Mail;
use App\Mail\SubInvitation;
use Spatie\Permission\Models\Role;

class SubInvitationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected $subInvitationService;
    protected $band;
    protected $owner;
    protected $event;

    protected function setUp(): void
    {
        parent::setUp();

        // Create sub role and permissions
        $this->artisan('db:seed', ['--class' => 'SubRolesPermissionsSeeder']);

        $this->subInvitationService = new SubInvitationService();

        // Create test data
        $this->band = Bands::factory()->create(['name' => 'Test Band']);
        $this->owner = User::factory()->create();

        BandOwners::create([
            'user_id' => $this->owner->id,
            'band_id' => $this->band->id
        ]);

        // Create a booking and event
        $booking = Bookings::factory()->create(['band_id' => $this->band->id]);
        $this->event = Events::factory()->create([
            'eventable_id' => $booking->id,
            'eventable_type' => 'App\Models\Bookings',
            'date' => now()->addDays(7),
            'title' => 'Test Event',
        ]);
    }

    public function test_can_invite_new_user_as_sub()
    {
        Mail::fake();

        $eventSub = $this->subInvitationService->inviteSubToEvent(
            eventId: $this->event->id,
            bandId: $this->band->id,
            email: 'newsub@example.com',
            name: 'New Sub',
            phone: '555-1234',
            payoutAmount: 15000,
            notes: 'Bring your own gear'
        );

        // Assert event_subs record created
        $this->assertDatabaseHas('event_subs', [
            'event_id' => $this->event->id,
            'band_id' => $this->band->id,
            'email' => 'newsub@example.com',
            'name' => 'New Sub',
            'payout_amount' => 15000,
            'pending' => true,
        ]);

        // Assert email was sent
        Mail::assertSent(SubInvitation::class, function ($mail) {
            return $mail->hasTo('newsub@example.com');
        });

        // Assert invitation key was generated
        $this->assertNotNull($eventSub->invitation_key);
        $this->assertEquals(36, strlen($eventSub->invitation_key));
    }

    public function test_can_invite_existing_user_as_sub()
    {
        Mail::fake();

        $existingUser = User::factory()->create(['email' => 'existing@example.com']);

        $eventSub = $this->subInvitationService->inviteSubToEvent(
            eventId: $this->event->id,
            bandId: $this->band->id,
            email: 'existing@example.com',
            name: 'Existing Sub'
        );

        // Assert user was linked
        $this->assertDatabaseHas('event_subs', [
            'event_id' => $this->event->id,
            'user_id' => $existingUser->id,
            'email' => 'existing@example.com',
        ]);

        // Assert added to band_subs
        $this->assertDatabaseHas('band_subs', [
            'user_id' => $existingUser->id,
            'band_id' => $this->band->id,
        ]);

        // Assert sub role assigned
        $this->assertTrue($existingUser->fresh()->hasRole('sub'));

        // Assert email was sent
        Mail::assertSent(SubInvitation::class);
    }

    public function test_can_accept_invitation()
    {
        $user = User::factory()->create();

        $eventSub = EventSubs::create([
            'event_id' => $this->event->id,
            'band_id' => $this->band->id,
            'email' => $user->email,
            'pending' => true,
        ]);

        $this->assertTrue($eventSub->isPending());

        $acceptedEventSub = $this->subInvitationService->acceptInvitation(
            $eventSub->invitation_key,
            $user
        );

        // Assert marked as accepted
        $this->assertFalse($acceptedEventSub->pending);
        $this->assertNotNull($acceptedEventSub->accepted_at);

        // Assert user linked
        $this->assertEquals($user->id, $acceptedEventSub->user_id);

        // Assert added to band_subs
        $this->assertDatabaseHas('band_subs', [
            'user_id' => $user->id,
            'band_id' => $this->band->id,
        ]);

        // Assert sub role assigned
        $this->assertTrue($user->fresh()->hasRole('sub'));
    }

    public function test_can_remove_sub_from_event()
    {
        $eventSub = EventSubs::create([
            'event_id' => $this->event->id,
            'band_id' => $this->band->id,
            'email' => 'remove@example.com',
        ]);

        $this->assertDatabaseHas('event_subs', ['id' => $eventSub->id]);

        $result = $this->subInvitationService->removeSubFromEvent($eventSub->id);

        $this->assertTrue($result);
        $this->assertDatabaseMissing('event_subs', ['id' => $eventSub->id]);
    }

    public function test_can_get_pending_invitations_for_user()
    {
        $user = User::factory()->create();

        // Create multiple invitations
        $pending1 = EventSubs::create([
            'event_id' => $this->event->id,
            'band_id' => $this->band->id,
            'user_id' => $user->id,
            'email' => $user->email,
            'pending' => true,
        ]);

        $booking2 = Bookings::factory()->create(['band_id' => $this->band->id]);
        $event2 = Events::factory()->create([
            'eventable_id' => $booking2->id,
            'eventable_type' => 'App\Models\Bookings',
        ]);

        $accepted = EventSubs::create([
            'event_id' => $event2->id,
            'band_id' => $this->band->id,
            'user_id' => $user->id,
            'email' => $user->email,
            'pending' => false,
        ]);

        $pendingInvitations = $this->subInvitationService->getPendingInvitationsForUser($user);

        // Should only return pending invitations
        $this->assertCount(1, $pendingInvitations);
        $this->assertEquals($pending1->id, $pendingInvitations->first()->id);
    }

    public function test_invitation_key_is_unique()
    {
        $eventSub1 = EventSubs::create([
            'event_id' => $this->event->id,
            'band_id' => $this->band->id,
            'email' => 'sub1@example.com',
        ]);

        $booking2 = Bookings::factory()->create(['band_id' => $this->band->id]);
        $event2 = Events::factory()->create([
            'eventable_id' => $booking2->id,
            'eventable_type' => 'App\Models\Bookings',
        ]);

        $eventSub2 = EventSubs::create([
            'event_id' => $event2->id,
            'band_id' => $this->band->id,
            'email' => 'sub2@example.com',
        ]);

        $this->assertNotEquals($eventSub1->invitation_key, $eventSub2->invitation_key);
    }

    public function test_cannot_add_same_user_to_event_twice()
    {
        $user = User::factory()->create();

        EventSubs::create([
            'event_id' => $this->event->id,
            'band_id' => $this->band->id,
            'user_id' => $user->id,
            'email' => $user->email,
        ]);

        // Attempting to create duplicate should fail due to unique constraint
        $this->expectException(\Illuminate\Database\QueryException::class);

        EventSubs::create([
            'event_id' => $this->event->id,
            'band_id' => $this->band->id,
            'user_id' => $user->id,
            'email' => $user->email,
        ]);
    }
}
