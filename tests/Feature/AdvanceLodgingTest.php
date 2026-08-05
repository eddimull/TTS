<?php

namespace Tests\Feature;

use App\Models\Bands;
use App\Models\Bookings;
use App\Models\Events;
use App\Models\EventTypes;
use App\Models\Lodging;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdvanceLodgingTest extends TestCase
{
    use RefreshDatabase;

    private function createEventWithLodging(): array
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $band = Bands::factory()->create();
        $band->owners()->create(['user_id' => $user->id]);
        $booking = Bookings::factory()->create(['band_id' => $band->id]);
        $event = Events::factory()->create([
            'eventable_id' => $booking->id, 'eventable_type' => 'App\\Models\\Bookings',
            'event_type_id' => EventTypes::factory()->create()->id,
            'date' => now()->addDays(5)->format('Y-m-d'),
        ]);
        $lodging = Lodging::factory()->create([
            'band_id' => $band->id, 'event_id' => $event->id,
            'name' => 'Advance Hotel', 'address' => '500 Beach Rd',
            'notes' => 'SECRET-NOTE',
        ]);
        $lodging->rooms()->create(['label' => 'King', 'confirmation_number' => 'SECRET-CONF', 'sort_order' => 0]);
        return compact('user', 'band', 'event');
    }

    public function test_advance_shows_lodging_logistics_only(): void
    {
        ['user' => $user, 'event' => $event] = $this->createEventWithLodging();

        $response = $this->actingAs($user)->get(route('events.advance', ['key' => $event->key]));

        $response->assertOk()
            ->assertSee('Advance Hotel')
            ->assertSee('500 Beach Rd')
            ->assertDontSee('SECRET-NOTE')
            ->assertDontSee('SECRET-CONF')
            ->assertDontSee('There will be lodging');
    }

    public function test_advance_without_lodging_shows_no_lodging_section(): void
    {
        ['user' => $user, 'event' => $event] = $this->createEventWithLodging();
        Lodging::where('event_id', $event->id)->forceDelete();

        $this->actingAs($user)
            ->get(route('events.advance', ['key' => $event->key]))
            ->assertOk()
            ->assertDontSee('Advance Hotel');
    }
}
