<?php

namespace Tests\Feature;

use App\Models\Bands;
use App\Models\Lodging;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LodgingWebTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_renders_for_band_owner(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $band = Bands::factory()->create();
        $band->owners()->create(['user_id' => $user->id]);
        Lodging::factory()->create(['band_id' => $band->id, 'name' => 'Visible Hotel']);

        $this->actingAs($user)
            ->get(route('lodgings.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Lodging/Index'));
    }

    public function test_store_creates_and_redirects(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $band = Bands::factory()->create();
        $band->owners()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->post(route('bands.lodgings.store', $band), [
                'name'         => 'Web Hotel',
                'check_in_at'  => now()->addDays(3)->format('Y-m-d H:i:s'),
                'check_out_at' => now()->addDays(4)->format('Y-m-d H:i:s'),
                'rooms'        => [['label' => 'King']],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('lodgings', ['name' => 'Web Hotel', 'band_id' => $band->id]);
        $this->assertDatabaseHas('lodging_rooms', ['label' => 'King']);
    }

    public function test_show_403s_for_stranger(): void
    {
        $stranger = User::factory()->create(['email_verified_at' => now()]);
        $lodging  = Lodging::factory()->create();

        $this->actingAs($stranger)
            ->get(route('lodgings.show', $lodging))
            ->assertStatus(403);
    }

    public function test_event_show_receives_lodgings_prop(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $band = Bands::factory()->create();
        $band->owners()->create(['user_id' => $user->id]);
        $booking = \App\Models\Bookings::factory()->create(['band_id' => $band->id]);
        $event = \App\Models\Events::factory()->create([
            'eventable_id' => $booking->id, 'eventable_type' => 'App\\Models\\Bookings',
            'event_type_id' => \App\Models\EventTypes::factory()->create()->id,
            'date' => now()->addDays(5)->format('Y-m-d'),
        ]);
        Lodging::factory()->create(['band_id' => $band->id, 'event_id' => $event->id, 'name' => 'Prop Hotel']);

        $this->actingAs($user)
            ->get(route('events.show', $event))
            ->assertOk()
            ->assertSee('Prop Hotel');
    }

    public function test_booking_show_receives_lodgings_prop(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $band = Bands::factory()->create();
        $band->owners()->create(['user_id' => $user->id]);
        $booking = \App\Models\Bookings::factory()->create(['band_id' => $band->id]);
        Lodging::factory()->create([
            'band_id' => $band->id, 'booking_id' => $booking->id, 'name' => 'Booking Prop Hotel',
        ]);

        $this->actingAs($user)
            ->get(route('Booking Details', [$band, $booking]))
            ->assertOk()
            ->assertSee('Booking Prop Hotel');
    }

    /**
     * events.show is guarded only by ['auth', 'verified'] — no band-membership
     * middleware — so an unaffiliated user reaches the page with a 200. The
     * lodgings prop must still be withheld from them.
     */
    public function test_event_show_hides_lodgings_from_non_member(): void
    {
        $stranger = User::factory()->create(['email_verified_at' => now()]);
        $band = Bands::factory()->create();
        $booking = \App\Models\Bookings::factory()->create(['band_id' => $band->id]);
        $event = \App\Models\Events::factory()->create([
            'eventable_id' => $booking->id, 'eventable_type' => 'App\\Models\\Bookings',
            'event_type_id' => \App\Models\EventTypes::factory()->create()->id,
            'date' => now()->addDays(5)->format('Y-m-d'),
        ]);
        Lodging::factory()->create([
            'band_id' => $band->id, 'event_id' => $event->id, 'name' => 'Secret Hotel',
        ]);

        $this->actingAs($stranger)
            ->get(route('events.show', $event))
            ->assertOk()
            ->assertDontSee('Secret Hotel')
            ->assertInertia(fn ($page) => $page->where('lodgings', []));
    }

    /**
     * The booking page is behind `booking.access`, which admits only owners and
     * members — a sub cannot reach it at all, so no prop-level gating is needed
     * there. Locks in the 403 that makes that reasoning safe.
     */
    public function test_booking_show_403s_for_non_member(): void
    {
        $stranger = User::factory()->create(['email_verified_at' => now()]);
        $band = Bands::factory()->create();
        $booking = \App\Models\Bookings::factory()->create(['band_id' => $band->id]);

        $this->actingAs($stranger)
            ->get(route('Booking Details', [$band, $booking]))
            ->assertStatus(403);
    }
}
