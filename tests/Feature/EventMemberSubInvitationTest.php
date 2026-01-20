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
use Illuminate\Support\Facades\Mail;
use App\Mail\SubInvitation;
use App\Mail\Invitation as BandInvitation;

class EventMemberSubInvitationTest extends TestCase
{
    use RefreshDatabase;

    protected $band;
    protected $owner;
    protected $event;

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

        $booking = Bookings::factory()->create(['band_id' => $this->band->id]);
        $this->event = Events::factory()->create([
            'eventable_id' => $booking->id,
            'eventable_type' => 'App\Models\Bookings',
            'date' => now()->addDays(7),
            'title' => 'Test Event',
        ]);
    }

    public function test_event_member_controller_sends_sub_invitation_not_band_invitation()
    {
        Mail::fake();

        // Add a member to an event with invite_substitute flag
        $response = $this->actingAs($this->owner)
            ->postJson("/events/{$this->event->id}/members", [
                'name' => 'New Sub Player',
                'email' => 'newsub@example.com',
                'phone' => '555-1234',
                'invite_substitute' => true,
            ]);

        $response->assertStatus(201);

        // Should send SubInvitation email, NOT band member invitation
        Mail::assertSent(SubInvitation::class, function ($mail) {
            return $mail->hasTo('newsub@example.com');
        });

        // Should NOT send band member invitation
        Mail::assertNotSent(BandInvitation::class);

        // Should create event_subs record
        $this->assertDatabaseHas('event_subs', [
            'event_id' => $this->event->id,
            'band_id' => $this->band->id,
            'email' => 'newsub@example.com',
            'pending' => true,
        ]);

        // Should NOT create band_members or invitations record
        $this->assertDatabaseMissing('invitations', [
            'email' => 'newsub@example.com',
            'band_id' => $this->band->id,
        ]);
    }

    public function test_event_member_invitation_links_existing_user()
    {
        Mail::fake();

        // Create an existing user
        $existingUser = User::factory()->create(['email' => 'existing@example.com']);

        // Add them to event with invite_substitute flag
        $response = $this->actingAs($this->owner)
            ->postJson("/events/{$this->event->id}/members", [
                'name' => 'Existing User',
                'email' => 'existing@example.com',
                'invite_substitute' => true,
            ]);

        $response->assertStatus(201);

        // Should send sub invitation
        Mail::assertSent(SubInvitation::class);

        // Should create event_subs record linked to user
        $this->assertDatabaseHas('event_subs', [
            'event_id' => $this->event->id,
            'user_id' => $existingUser->id,
            'email' => 'existing@example.com',
        ]);

        // Should add to band_subs
        $this->assertDatabaseHas('band_subs', [
            'user_id' => $existingUser->id,
            'band_id' => $this->band->id,
        ]);

        // User should have sub role
        $this->assertTrue($existingUser->fresh()->hasRole('sub'));
    }

    public function test_event_member_without_invite_flag_does_not_send_invitation()
    {
        Mail::fake();

        // Add member without invite_substitute flag
        $response = $this->actingAs($this->owner)
            ->postJson("/events/{$this->event->id}/members", [
                'name' => 'Regular Member',
                'email' => 'regular@example.com',
                'invite_substitute' => false,
            ]);

        $response->assertStatus(201);

        // Should NOT send any invitation email
        Mail::assertNothingSent();

        // Should NOT create event_subs record
        $this->assertDatabaseMissing('event_subs', [
            'email' => 'regular@example.com',
        ]);
    }

    public function test_event_member_invitation_requires_email()
    {
        $response = $this->actingAs($this->owner)
            ->postJson("/events/{$this->event->id}/members", [
                'name' => 'No Email Sub',
                'invite_substitute' => true,
                // email missing
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Email is required to invite a substitute'
            ]);
    }
}
