<?php

namespace Database\Factories;

use App\Models\Bands;
use App\Models\Bookings;
use App\Models\Contacts;
use Illuminate\Database\Eloquent\Factories\Factory;
use Carbon\Carbon;

class BookingsFactory extends Factory
{
    protected $model = Bookings::class;


    public function withContacts()
    {
        return $this->afterCreating(function (Bookings $booking)
        {
            // Create and attach a primary contact
            $booking->contacts()->attach(
                Contacts::factory()->create(),
                [
                    'role' => $this->faker->jobTitle,
                    'is_primary' => true,
                    'notes' => $this->faker->sentence,
                    'additional_info' => json_encode(['preference' => $this->faker->word])
                ]
            );

            // 70% chance to add a secondary contact
            if ($this->faker->boolean(70))
            {
                $booking->contacts()->attach(
                    Contacts::factory()->create(),
                    [
                        'role' => $this->faker->jobTitle,
                        'is_primary' => false,
                        'notes' => $this->faker->sentence,
                        'additional_info' => json_encode(['preference' => $this->faker->word])
                    ]
                );
            }
        });
    }

    public function definition()
    {
        $startDate = $this->faker->dateTimeBetween('now', '+1 year');
        $endDate = (clone $startDate)->modify('+' . $this->faker->numberBetween(1, 6) . ' hours');

        $band = Bands::factory()->withOwners()->create();

        return [
            'band_id' => $band->id,
            'author_id' => $band->owners->first()->user_id,
            'name' => $this->faker->sentence,
            'event_type_id' => $this->faker->numberBetween(1, 6),
            'date' => $startDate->format('Y-m-d'),
            'start_time' => $startDate->format('H:i'),
            'end_time' => $endDate->format('H:i'),
            'venue_name' => $this->faker->company,
            'venue_address' => $this->faker->address,
            'price' => $this->faker->numberBetween(500, 10000),
            'status' => $this->faker->randomElement(['pending', 'confirmed', 'cancelled']),
            'contract_option' => $this->faker->randomElement(['default', 'none', 'external']),
            'notes' => $this->faker->optional()->text,
        ];
    }

    public function confirmed()
    {
        return $this->state(function (array $attributes)
        {
            return [
                'status' => 'confirmed',
            ];
        });
    }

    public function cancelled()
    {
        return $this->state(function (array $attributes)
        {
            return [
                'status' => 'cancelled',
            ];
        });
    }

    public function duration($hours)
    {
        return $this->state(function (array $attributes) use ($hours)
        {
            $startDate = Carbon::parse($attributes['date'] . ' ' . $attributes['start_time']);
            $endDate = $startDate->copy()->addHours($hours);

            return [
                'end_time' => $endDate->format('H:i'),
            ];
        });
    }

    public function pending()
    {
        return $this->state(function (array $attributes)
        {
            return [
                'status' => 'pending',
            ];
        });
    }

    public function forBand(Bands $band)
    {
        return $this->state(function (array $attributes) use ($band)
        {
            return [
                'band_id' => $band->id,
            ];
        });
    }
}
