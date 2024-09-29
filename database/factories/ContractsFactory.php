<?php

namespace Database\Factories;

use App\Models\Contracts;
use Illuminate\Database\Eloquent\Factories\Factory;

class ContractsFactory extends Factory
{
    protected $model = Contracts::class;

    public function definition()
    {
        return [
            'envelope_id' => $this->faker->uuid,
            'author_id' => \App\Models\User::factory(),
            'status' => 'pending',
            'asset_url' => $this->faker->url,
            'custom_terms' => [
                ['title' => 'Term 1', 'content' => 'Content 1'],
                ['title' => 'Term 2', 'content' => 'Content 2'],
            ],
        ];
    }
}
