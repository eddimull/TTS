<?php

namespace Database\Factories;

use App\Models\ProposalContacts;
use App\Models\Proposals;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProposalContactsFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = ProposalContacts::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'proposal_id' => Proposals::factory(),
            'email' => $this->faker->safeEmail(),
            'name' => $this->faker->name(),
            'phonenumber' => $this->faker->phoneNumber()
        ];
    }

    /**
     * Indicate that the proposal contact belongs to a specific proposal.
     *
     * @param  int  $proposalId
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function forProposal($proposalId)
    {
        return $this->state(function (array $attributes) use ($proposalId)
        {
            return [
                'proposal_id' => $proposalId,
            ];
        });
    }
}
