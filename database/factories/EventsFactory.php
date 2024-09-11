<?php

namespace Database\Factories;

use App\Models\Bands;
use App\Models\Events;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;

class EventsFactory extends Factory
{
    protected $model = Events::class;

    public function definition(): array
    {
        $startDate = $this->faker->dateTimeBetween('now', '+1 year');

        return [
            'band_id' => Bands::factory(),
            'event_type_id' => $this->faker->numberBetween(1, 6),
            'date' => $startDate->format('Y-m-d'),
            'time' => $startDate->format('H:i'),
            'key' => $this->faker->uuid,
            'title' => $this->faker->sentence,
            'notes' => $this->faker->optional()->text,
        ];
    }

    public function withAdditionalData(array $data): EventsFactory
    {
        return $this->state(function (array $attributes) use ($data) {
            return [
                'additional_data' => $data,
            ];
        });
    }

    public function forBand(Bands $band): EventsFactory
    {
        return $this->state(function (array $attributes) use ($band)
        {
            return [
                'band_id' => $band->id,
            ];
        });
    }

    public function withEventable(Model $eventable): EventsFactory {
        return $this->state(function (array $attributes) use ($eventable) {
            return [
                'eventable_type' => $eventable->getMorphClass(),
                'eventable_id' => $eventable->id,
            ];
        });
    }
}
