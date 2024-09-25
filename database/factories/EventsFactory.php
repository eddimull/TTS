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
    protected $band_id;

    public function definition()
    {
        return [
            'additional_data' => json_encode([
                'key1' => $this->faker->word,
                'key2' => $this->faker->numberBetween(1, 100),
                'key3' => $this->faker->sentence,
            ]),
            'date' => $this->faker->date(),
            'event_type_id' => function ()
            {
                return EventTypes::inRandomOrder()->first()->id ?? EventTypes::factory()->create()->id;
            },
            'eventable_id' => function ()
            {
                $bandId = $this->band_id ?? (Bands::inRandomOrder()->first()->id ?? Bands::factory()->create()->id);
                return Bookings::factory()->create(['band_id' => $bandId])->id;
            },
            'eventable_type' => Bookings::class,
            'key' => $this->faker->uuid,
            'title' => $this->faker->sentence,
            'notes' => $this->faker->paragraph,
            'time' => $this->faker->time(),
        ];
    }

    public function forBand(Bands $band)
    {
        $this->band_id = $band->id;
        return $this;
    }
}
