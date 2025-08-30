<?php

namespace Database\Factories;

use App\Models\Contracts;
use Illuminate\Support\Facades\Storage;
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
            'custom_terms' => Storage::disk('local')->json('contract/InitialTerms.json'),
        ];
    }
}
