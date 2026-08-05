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

class DashboardLodgingTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_events_carry_lodging_logistics_summary(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $band = Bands::factory()->create();
        $band->owners()->create(['user_id' => $user->id]);
        $booking = Bookings::factory()->create(['band_id' => $band->id]);
        $event = Events::factory()->create([
            'eventable_id' => $booking->id, 'eventable_type' => 'App\\Models\\Bookings',
            'event_type_id' => EventTypes::factory()->create()->id,
            'date' => now()->addDays(3)->format('Y-m-d'),
        ]);
        Lodging::factory()->create([
            'band_id' => $band->id, 'event_id' => $event->id,
            'name' => 'Dash Hotel', 'notes' => 'SECRET-NOTE',
        ]);

        $response = $this->actingAs($user)->get('/dashboard');
        $response->assertOk();

        $events = collect($response->viewData('page')['props']['events']);
        $withLodging = $events->firstWhere('id', $event->id);
        $this->assertSame('Dash Hotel', $withLodging['lodgings_summary'][0]['name']);
        $this->assertStringNotContainsString('SECRET-NOTE', json_encode($withLodging['lodgings_summary']));
    }
}
