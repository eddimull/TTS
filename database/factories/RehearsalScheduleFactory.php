<?php

namespace Database\Factories;

use App\Models\Bands;
use App\Models\RehearsalSchedule;
use Illuminate\Database\Eloquent\Factories\Factory;

class RehearsalScheduleFactory extends Factory
{
    protected $model = RehearsalSchedule::class;

    public function definition()
    {
        $frequency = $this->faker->randomElement(['weekly', 'biweekly', 'monthly', 'custom']);

        return [
            'band_id' => Bands::factory(),
            'name' => 'Weekly Rehearsal',
            'description' => $this->faker->optional()->sentence,
            'frequency' => $frequency,
            'day_of_week' => in_array($frequency, ['weekly', 'biweekly'])
                ? $this->faker->randomElement(['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'])
                : null,
            'selected_days' => null,
            'day_of_month' => null,
            'monthly_pattern' => null,
            'monthly_weekday' => null,
            'default_time' => '19:00:00',
            'location_name' => $this->faker->company . ' Studio',
            'location_address' => $this->faker->address,
            'notes' => $this->faker->optional()->paragraph,
            'active' => true,
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

    public function inactive()
    {
        return $this->state(function (array $attributes) {
            return [
                'active' => false,
            ];
        });
    }

    public function weekly()
    {
        return $this->state(function (array $attributes) {
            return [
                'frequency' => 'weekly',
                'day_of_week' => 'wednesday',
            ];
        });
    }

    public function monthly()
    {
        return $this->state(function (array $attributes) {
            return [
                'frequency' => 'monthly',
                'day_of_month' => 15,
            ];
        });
    }
}
