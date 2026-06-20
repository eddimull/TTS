<?php

namespace Database\Factories;

use App\Models\BandSubInvitation;
use App\Models\Bands;
use Illuminate\Database\Eloquent\Factories\Factory;

class BandSubInvitationFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = BandSubInvitation::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'band_id' => Bands::factory(),
            'band_role_id' => null,
            'user_id' => null,
            'email' => $this->faker->unique()->safeEmail(),
            'name' => $this->faker->name(),
            'phone' => $this->faker->phoneNumber(),
            'pending' => true,
            'accepted_at' => null,
            'notes' => null,
        ];
    }

    /**
     * Indicate that the invitation has been accepted.
     */
    public function accepted()
    {
        return $this->state(fn (array $attributes) => [
            'pending' => false,
            'accepted_at' => now(),
        ]);
    }
}
