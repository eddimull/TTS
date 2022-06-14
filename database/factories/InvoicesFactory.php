<?php

namespace Database\Factories;

use App\Models\Invoices;
use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Proposals;

class InvoicesFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Invoices::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        $proposal = Proposals::factory()->create();
        return [
            'proposal_id'=> $proposal->id,
            'amount'=>$proposal->price/2,
            'status'=>'open',
            'stripe_id'=>'in_1234',
            'convenience_fee'=>true
        ];
    }
}
