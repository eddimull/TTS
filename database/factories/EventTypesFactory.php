<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\EventTypes>
 */
class EventTypesFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
    $event_types =[
        [
            'name'=>'Wedding'
        ],
        [
            'name'=>'Bar Gig'
        ],
        [
            'name'=>'Casino'
        ],
        [
            'name'=>'Special Event'
        ],
        [
            'name'=>'Charity'
        ],
        [
            'name'=>'Festival'
        ],
        [
            'name'=>'Private Party'
        ],
        [
            'name'=>'Mardi Gras Ball'
        ],        
        [
            'name'=>'Other'
        ]
        ];
        return ['name' => $this->faker->randomElement($event_types)['name']];
    }
}
