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

class SubInvitationAcceptanceTest extends TestCase
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
        ]);
    }

    public function test_authenticated_user_clicking_invitation_link_accepts_and_redirects_to_dashboard()
    {
        // Create a user and sub invitation
        $subUser = User::factory()->create(['email' => 'sub@example.com']);
        $subUser->assignRole('sub');

        $eventSub = EventSubs::create([
            'event_id' => $this->event->id,
            'band_id' => $this->band->id,
            'user_id' => $subUser->id,
            'email' => $subUser->email,
            'pending' => true,
        ]);

        // User is already logged in and clicks invitation link
        $response = $this->actingAs($subUser)
            ->get("/sub/invitation/{$eventSub->invitation_key}");

        // Should redirect to dashboard (not throw route error)
        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('success');

        // Verify invitation was accepted
        $eventSub->refresh();
        $this->assertFalse($eventSub->pending);
        $this->assertNotNull($eventSub->accepted_at);
    }

    public function test_unauthenticated_user_clicking_invitation_link_sees_invitation_page()
    {
        // Create a sub invitation for non-registered user
        $eventSub = EventSubs::create([
            'event_id' => $this->event->id,
            'band_id' => $this->band->id,
            'email' => 'newsub@example.com',
            'name' => 'New Sub',
            'pending' => true,
        ]);

        // Unauthenticated user clicks invitation link
        $response = $this->get("/sub/invitation/{$eventSub->invitation_key}");

        // Should show invitation page (not redirect)
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('SubInvitation/Show')
            ->where('eventSub.email', 'newsub@example.com')
        );

        // Invitation should still be pending
        $eventSub->refresh();
        $this->assertTrue($eventSub->pending);
    }

    public function test_accepting_already_accepted_invitation_redirects_to_dashboard()
    {
        // Create user and already-accepted invitation
        $subUser = User::factory()->create(['email' => 'sub@example.com']);
        $subUser->assignRole('sub');

        $eventSub = EventSubs::create([
            'event_id' => $this->event->id,
            'band_id' => $this->band->id,
            'user_id' => $subUser->id,
            'email' => $subUser->email,
            'pending' => false,
            'accepted_at' => now()->subDay(),
        ]);

        // User clicks invitation link again
        $response = $this->actingAs($subUser)
            ->get("/sub/invitation/{$eventSub->invitation_key}");

        // Should redirect to dashboard without error
        $response->assertRedirect(route('dashboard'));
    }

    public function test_accept_endpoint_redirects_to_dashboard()
    {
        // Create user and pending invitation
        $subUser = User::factory()->create(['email' => 'sub@example.com']);
        $subUser->assignRole('sub');

        $eventSub = EventSubs::create([
            'event_id' => $this->event->id,
            'band_id' => $this->band->id,
            'user_id' => $subUser->id,
            'email' => $subUser->email,
            'pending' => true,
        ]);

        // Call the explicit accept endpoint
        $response = $this->actingAs($subUser)
            ->post("/sub/invitation/{$eventSub->invitation_key}/accept");

        // Should redirect to dashboard (not throw route error)
        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('success');

        // Verify invitation was accepted
        $eventSub->refresh();
        $this->assertFalse($eventSub->pending);
    }
}
