<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Bands;
use App\Models\Bookings;
use App\Models\Events;
use App\Models\EventTypes;
use App\Models\userPermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;

class EventsShowTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $band;
    protected $booking;
    protected $event;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a user
        $this->user = User::factory()->create();

        // Create a band with the user as owner
        $this->band = Bands::factory()->create();
        $this->band->owners()->create([
            'user_id' => $this->user->id,
        ]);

        // Create a booking
        $this->booking = Bookings::factory()->create([
            'band_id' => $this->band->id,
        ]);

        // Create an event
        $eventType = EventTypes::factory()->create();
        $this->event = Events::factory()->create([
            'eventable_id' => $this->booking->id,
            'eventable_type' => 'App\\Models\\Bookings',
            'event_type_id' => $eventType->id,
        ]);
    }

    public function test_can_view_event_show_page_with_key()
    {
        $response = $this->actingAs($this->user)
            ->get(route('events.show', $this->event->key));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Events/Show')
            ->has('event')
            ->has('canEdit')
            ->has('band')
        );
    }

    public function test_can_view_event_show_page_with_id()
    {
        // Test backwards compatibility with numeric IDs
        $response = $this->actingAs($this->user)
            ->get(route('events.show', $this->event->id));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Events/Show')
            ->where('event.id', $this->event->id)
            ->has('event')
            ->has('canEdit')
            ->has('band')
        );
    }

    public function test_show_page_loads_necessary_relationships()
    {
        $response = $this->actingAs($this->user)
            ->get(route('events.show', $this->event->key));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Events/Show')
            ->where('event.id', $this->event->id)
            ->has('event.eventable')
            ->has('event.type')
        );
    }

    public function test_band_owner_can_edit()
    {
        // Band owners automatically have write permission
        $response = $this->actingAs($this->user)
            ->get(route('events.show', $this->event->key));

        $response->assertInertia(fn ($page) => $page
            ->where('canEdit', true)
        );
    }

    public function test_non_owner_without_write_permission_cannot_edit()
    {
        // Create a different user who is not a band owner
        $nonOwner = User::factory()->create();

        // Add as band member but with no write permissions
        $this->band->members()->create([
            'user_id' => $nonOwner->id,
        ]);

        $response = $this->actingAs($nonOwner)
            ->get(route('events.show', $this->event->key));

        $response->assertInertia(fn ($page) => $page
            ->where('canEdit', false)
        );
    }

    public function test_non_owner_with_write_permission_can_edit()
    {
        // Create a different user who is not a band owner
        $member = User::factory()->create();

        // Add as band member with write permissions
        $this->band->members()->create([
            'user_id' => $member->id,
        ]);

        // Give write permissions
        userPermissions::create([
            'user_id' => $member->id,
            'band_id' => $this->band->id,
            'write_events' => true,
        ]);

        $response = $this->actingAs($member)
            ->get(route('events.show', $this->event->key));

        $response->assertInertia(fn ($page) => $page
            ->where('canEdit', true)
        );
    }

    public function test_requires_authentication()
    {
        $response = $this->get(route('events.show', $this->event->key));

        $response->assertRedirect(route('login'));
    }

    public function test_returns_404_for_nonexistent_event()
    {
        $response = $this->actingAs($this->user)
            ->get(route('events.show', 'nonexistent-key'));

        $response->assertStatus(404);
    }
}
