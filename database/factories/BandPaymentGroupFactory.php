<?php

namespace Database\Factories;

use App\Models\BandPaymentGroup;
use App\Models\Bands;
use Illuminate\Database\Eloquent\Factories\Factory;

class BandPaymentGroupFactory extends Factory
{
    protected $model = BandPaymentGroup::class;

    public function definition(): array
    {
        return [
            'band_id' => Bands::factory(),
            'name' => $this->faker->unique()->words(2, true),
            'description' => $this->faker->optional()->sentence(),
            'default_payout_type' => $this->faker->randomElement(['percentage', 'fixed', 'equal_split']),
            'default_payout_value' => $this->faker->optional()->randomFloat(2, 0, 1000),
            'display_order' => $this->faker->numberBetween(0, 10),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function equalSplit(): static
    {
        return $this->state(fn (array $attributes) => [
            'default_payout_type' => 'equal_split',
            'default_payout_value' => null,
        ]);
    }

    public function fixed(float $amount = 500.00): static
    {
        return $this->state(fn (array $attributes) => [
            'default_payout_type' => 'fixed',
            'default_payout_value' => $amount,
        ]);
    }

    public function percentage(float $value = 15.0): static
    {
        return $this->state(fn (array $attributes) => [
            'default_payout_type' => 'percentage',
            'default_payout_value' => $value,
        ]);
    }
}
