<?php

namespace Database\Factories;

use App\Models\BandEvents;
use App\Models\Model;
use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Bands;
use Carbon\Carbon;
use Illuminate\Support\Str;
use App\Models\EventTypes;
use App\Models\State;

class BandEventsFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = BandEvents::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        $band = Bands::factory()->create();
        $eventDate = $this->faker->dateTimeBetween($startDate = 'now', $endDate = '3 years');
        return [
            'band_id'=>$band->id,
            'event_name'=>"Test Event " . $this->faker->name(),
            'first_dance'=>'TBD',
            'father_daughter'=>'TBD',
            'money_dance'=>'TBD',
            'bouquet_garter'=>'TBD',
            'address_street'=>'417 Jefferson St, Lafayette, LA ',
            'zip'=>'70501',
            'notes'=>'Made with factory',
            'event_time'=>Carbon::parse($eventDate),
            'band_loadin_time'=>Carbon::parse($eventDate)->subHours(2),
            'rhythm_loadin_time'=>Carbon::parse($eventDate)->subHours(3),
            'production_loadin_time'=>Carbon::parse($eventDate)->subHours(4),
            'pay'=>number_format($this->faker->numberBetween(1000,25000),2,'.',''),
            'depositReceived'=>1,
            'event_key'=>Str::uuid(),
            'created_at'=>Carbon::now(),
            'updated_at'=>Carbon::now(),
            'public'=>$this->faker->boolean(),
            'event_type_id'=>EventTypes::inRandomOrder()->first()->id,
            'lodging'=>$this->faker->boolean(20),
            'state_id'=>1,
            'colorway_id'=>0,
            'city'=>'Lafayette',
            'outside'=>$this->faker->boolean(),
            'second_line'=>$this->faker->boolean(10),
            'onsite'=>$this->faker->boolean(25),
            'quiet_time'=>Carbon::parse($eventDate)->subHours(1),
            'end_time'=>Carbon::parse($eventDate)->addHours(4),
            'mother_groom'=>'TBD',
            'production_needed'=>$this->faker->boolean(),
            'backline_provided'=>$this->faker->boolean()
        ];
    }
}
