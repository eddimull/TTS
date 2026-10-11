<?php

namespace Database\Factories;

use App\Models\Bands;
use App\Models\Events;
use App\Models\Bookings;
use App\Models\EventType;
use App\Models\EventTypes;
use Illuminate\Database\Eloquent\Factories\Factory;

class EventsFactory extends Factory
{
    protected $model = Events::class;

    public function definition()
    {
        return [
            'additional_data' => [
                'key1' => $this->faker->word,
                'key2' => $this->faker->numberBetween(1, 100),
                'key3' => $this->faker->sentence,
            ],
            'date' => $this->faker->date(),
            'event_type_id' => function ()
            {
                return EventTypes::inRandomOrder()->first()->id ?? EventTypes::factory()->create()->id;
            },
            'eventable_id' => function ()
            {
                $bandId = Bands::inRandomOrder()->first()->id ?? Bands::factory()->create()->id;
                return Bookings::factory()->create(['band_id' => $bandId])->id;
            },
            'eventable_type' => Bookings::class,
            'key' => $this->faker->uuid,
            'title' => $this->faker->sentence,
            'notes' => $this->faker->paragraph,
            'start_time' => null,
            'end_time' => null,
            'venue_name' => null,
            'venue_address' => null,
            'price' => null,
        ];
    }

    /**
     * Attach the event to a booking of the given band. This must be a state,
     * not a property: create([...]) clones the factory, which would drop it
     * and fall back to a random band.
     */
    public function forBand(Bands $band)
    {
        return $this->state(fn () => [
            'eventable_id' => fn () => Bookings::factory()->create(['band_id' => $band->id])->id,
            'eventable_type' => Bookings::class,
        ]);
    }
}
