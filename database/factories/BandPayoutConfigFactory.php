<?php

namespace Database\Factories;

use App\Models\BandPayoutConfig;
use App\Models\Bands;
use Illuminate\Database\Eloquent\Factories\Factory;

class BandPayoutConfigFactory extends Factory
{
    protected $model = BandPayoutConfig::class;

    public function definition(): array
    {
        return [
            'band_id' => Bands::factory(),
            'name' => $this->faker->words(3, true),
            'is_active' => false,
            'band_cut_type' => 'percentage',
            'band_cut_value' => 0,
            'member_payout_type' => 'equal_split',
            'include_owners' => true,
            'include_members' => true,
        ];
    }
}
