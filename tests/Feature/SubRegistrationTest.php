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

class SubRegistrationTest extends TestCase
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

    public function test_register_page_autopopulates_name_and_email_from_sub_invitation()
    {
        // Create a sub invitation
        $eventSub = EventSubs::create([
            'event_id' => $this->event->id,
            'band_id' => $this->band->id,
            'email' => 'newsubstitute@example.com',
            'name' => 'John Substitute',
            'pending' => true,
        ]);

        // Visit register page with invitation key
        $response = $this->get("/register?invitation={$eventSub->invitation_key}");

        $response->assertStatus(200)
            ->assertInertia(fn ($page) => $page
                ->component('Auth/Register')
                ->where('invitationEmail', 'newsubstitute@example.com')
                ->where('invitationName', 'John Substitute')
            );
    }

    public function test_register_page_handles_invitation_without_name()
    {
        // Create a sub invitation without a name
        $eventSub = EventSubs::create([
            'event_id' => $this->event->id,
            'band_id' => $this->band->id,
            'email' => 'newsubstitute@example.com',
            'pending' => true,
        ]);

        // Visit register page with invitation key
        $response = $this->get("/register?invitation={$eventSub->invitation_key}");

        $response->assertStatus(200)
            ->assertInertia(fn ($page) => $page
                ->component('Auth/Register')
                ->where('invitationEmail', 'newsubstitute@example.com')
                ->where('invitationName', null)
            );
    }

    public function test_registering_with_sub_invitation_auto_accepts_invitation()
    {
        // Create a sub invitation
        $eventSub = EventSubs::create([
            'event_id' => $this->event->id,
            'band_id' => $this->band->id,
            'email' => 'newsubstitute@example.com',
            'name' => 'John Substitute',
            'pending' => true,
        ]);

        // Register a new user with the same email
        $response = $this->post('/register', [
            'name' => 'John Substitute',
            'email' => 'newsubstitute@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect();

        // Verify the invitation was accepted
        $eventSub->refresh();
        $this->assertFalse($eventSub->pending);
        $this->assertNotNull($eventSub->accepted_at);
        $this->assertNotNull($eventSub->user_id);

        // Verify user was assigned sub role
        $user = User::where('email', 'newsubstitute@example.com')->first();
        $this->assertTrue($user->hasRole('sub'));
    }

    public function test_registering_accepts_multiple_sub_invitations()
    {
        // Create multiple events
        $booking2 = Bookings::factory()->create(['band_id' => $this->band->id]);
        $event2 = Events::factory()->create([
            'eventable_id' => $booking2->id,
            'eventable_type' => 'App\Models\Bookings',
            'date' => now()->addDays(14),
        ]);

        // Create sub invitations for both events
        $eventSub1 = EventSubs::create([
            'event_id' => $this->event->id,
            'band_id' => $this->band->id,
            'email' => 'newsubstitute@example.com',
            'name' => 'John Substitute',
            'pending' => true,
        ]);

        $eventSub2 = EventSubs::create([
            'event_id' => $event2->id,
            'band_id' => $this->band->id,
            'email' => 'newsubstitute@example.com',
            'name' => 'John Substitute',
            'pending' => true,
        ]);

        // Register a new user
        $this->post('/register', [
            'name' => 'John Substitute',
            'email' => 'newsubstitute@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        // Verify both invitations were accepted
        $eventSub1->refresh();
        $eventSub2->refresh();

        $this->assertFalse($eventSub1->pending);
        $this->assertFalse($eventSub2->pending);
        $this->assertNotNull($eventSub1->accepted_at);
        $this->assertNotNull($eventSub2->accepted_at);
    }

    public function test_register_page_without_invitation_has_null_values()
    {
        // Visit register page without invitation key
        $response = $this->get('/register');

        $response->assertStatus(200)
            ->assertInertia(fn ($page) => $page
                ->component('Auth/Register')
                ->where('invitationEmail', null)
                ->where('invitationName', null)
            );
    }
}
