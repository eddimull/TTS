<?php

namespace Database\Factories;

use App\Models\ProposalPayments;
use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Proposals;

class ProposalPaymentsFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = ProposalPayments::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        $proposal = Proposals::factory()->create();
        return [
            'name'=>'Test Payment',
            'proposal_id'=> $proposal->id,
            'amount'=>$proposal->price/2,
        ];
    }
}
