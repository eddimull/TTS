<?php

namespace Database\Factories;

use App\Models\Bands;
use App\Models\Proposals;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class ProposalsFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Proposals::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        $eventDate = $this->faker->dateTimeBetween($startDate = 'now', $endDate = '3 years');
        $band = Bands::factory()->hasOwner()->create();
        $owner = $band->owner[0]->user;
        
        return [
            'band_id'=>$band->id,
            'phase_id'=>$this->faker->numberBetween(1,6),
            'author_id'=>$owner->id,
            'date'=>Carbon::parse($eventDate),
            'hours'=>$this->faker->numberBetween(1,6),
            'price'=>number_format($this->faker->numberBetween(1000,25000),2,'.',''),
            'name'=>$this->faker->company(),
            'locked'=>false,
            'key'=>Str::uuid(),
            'event_type_id'=>$this->faker->numberBetween(1,9),
            'location'=>$this->faker->address(),
            'paid'=>$this->faker->numberBetween(0,1)
        ];
    }
}
