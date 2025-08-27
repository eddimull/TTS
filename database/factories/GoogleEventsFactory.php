<?php

namespace Database\Factories;

use App\Models\Bands;
use App\Models\Events;
use App\Models\BandCalendars;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\GoogleEvents>
 */
class GoogleEventsFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $band = Bands::factory()->create();
        $calendar = BandCalendars::factory()->create([
            'band_id' => $band->id,
            'type' => 'event'
        ]);
        $event = Events::factory()->forBand($band)->create();
        return [
            'google_event_id' => $this->faker->uuid(),
            'google_eventable_id' => $event->id,
            'google_eventable_type' => get_class($event),
            'band_calendar_id' => $calendar->id
        ];
    }
}
