<?php

namespace Database\Factories;

use App\Models\Bands;
use App\Models\Rehearsal;
use App\Models\RehearsalSchedule;
use Illuminate\Database\Eloquent\Factories\Factory;

class RehearsalFactory extends Factory
{
    protected $model = Rehearsal::class;

    public function definition()
    {
        return [
            'rehearsal_schedule_id' => RehearsalSchedule::factory(),
            'band_id' => Bands::factory(),
            'venue_name' => $this->faker->company . ' Studio',
            'venue_address' => $this->faker->address,
            'notes' => $this->faker->optional()->paragraph,
            'additional_data' => null,
            'is_cancelled' => false,
        ];
    }

    public function forBand(Bands $band)
    {
        return $this->state(function (array $attributes) use ($band) {
            return [
                'band_id' => $band->id,
            ];
        });
    }

    public function forSchedule(RehearsalSchedule $schedule)
    {
        return $this->state(function (array $attributes) use ($schedule) {
            return [
                'rehearsal_schedule_id' => $schedule->id,
                'band_id' => $schedule->band_id,
            ];
        });
    }

    public function cancelled()
    {
        return $this->state(function (array $attributes) {
            return [
                'is_cancelled' => true,
            ];
        });
    }

    public function withPerformanceData()
    {
        return $this->state(function (array $attributes) {
            return [
                'additional_data' => (object)[
                    'charts' => [
                        (object)[
                            'id' => $this->faker->numberBetween(1, 100),
                            'title' => $this->faker->words(3, true),
                            'composer' => $this->faker->name,
                        ]
                    ],
                    'songs' => [
                        (object)[
                            'title' => $this->faker->words(3, true),
                            'url' => $this->faker->url,
                        ]
                    ]
                ],
            ];
        });
    }
}
