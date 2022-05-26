<?php

namespace Database\Factories;

use App\Models\Invitations;
use Illuminate\Database\Eloquent\Factories\Factory;

class InvitationsFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Invitations::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'email'=>$this->faker->safeEmail(),
            'band_id'=>1,
            'invite_type_id'=>1
        ];
    }
}
