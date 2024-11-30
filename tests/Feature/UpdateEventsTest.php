<?php

namespace Tests\Feature;

use App\Models\BandEvents;
use App\Models\BandMembers;
use App\Models\BandOwners;
use App\Models\Bands;
use App\Models\User;
use Database\Factories\BandEventsFactory;
use Illuminate\Support\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class UpdateEventsTest extends TestCase
{
    use WithFaker;
    /**
     * A basic feature test example.
     *
     * @return void
     */
    public function test_update_event()
    {
        $band = Bands::factory()->create();
        $user = User::factory()->create();

        BandOwners::create([
            'band_id' => $band->id,
            'user_id' => $user->id
        ]);


        $event = BandEvents::factory()->create([
            'band_id' => $band->id
        ]);
        // Log::info('Event object:', [$event]);
        // dd(BandEvents::where('band_id', $band->id)->get());

        $response = $this->actingAs($user)->patch(
            '/events/' . $event->key,
            [
                'event_key' => $event->key,
                'band_id' => $band->id,
                'event_name' => 'Test Event!',
                'city' => 'Rack City',
                'venue_name' => 'Test Venue',
                'event_type_id' => 1,
                'outside' => 0,
            ]
        );

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('band_events', [
            'event_key' => $event->key,
            'event_name' => 'Test Event!',
            'city' => 'Rack City',
        ]);
    }
}
