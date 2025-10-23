<?php

namespace Database\Factories;

use App\Models\BandMembers;
use App\Models\BandOwners;
use App\Models\Bands;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class BandsFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Bands::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        $band = $this->faker->company();
        return [
            'name' => $band,
            'site_name' => Str::slug($band, '_')
        ];
    }

    public function withOwners()
    {
        return $this->afterCreating(function (Bands $band)
        {
            $band->owners()->create(['user_id' => User::factory()->create()->id]);
        });
    }

    public function hasOwner()
    {
        return $this->afterCreating(function (Bands $band)
        {
            BandOwners::factory()->create([
                'band_id' => $band->id,
                'user_id' => User::factory()->create()->id
            ]);
        });
    }

    public function hasOwners($count = 1)
    {
        return $this->afterCreating(function (Bands $band) use ($count)
        {
            for ($i = 0; $i < $count; $i++) {
                BandOwners::factory()->create([
                    'band_id' => $band->id,
                    'user_id' => User::factory()->create()->id
                ]);
            }
        });
    }

    public function hasMember()
    {
        return $this->afterCreating(function (Bands $band)
        {
            BandMembers::factory()->create([
                'band_id' => $band->id,
                'user_id' => User::factory()->create()->id
            ]);
        });
    }

    public function hasMembers($count = 1)
    {
        return $this->afterCreating(function (Bands $band) use ($count)
        {
            for ($i = 0; $i < $count; $i++) {
                BandMembers::factory()->create([
                    'band_id' => $band->id,
                    'user_id' => User::factory()->create()->id
                ]);
            }
        });
    }
}
