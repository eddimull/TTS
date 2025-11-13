<?php

namespace Database\Factories;

use App\Models\Invoices;
use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Bookings;

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
        $booking = Bookings::factory()->create();
        return [
            'booking_id' => $booking->id,
            'amount' => $booking->price,
            'status' => 'open',
            'stripe_id' => 'in_1234',
            'stripe_url' => 'https://invoice.stripe.com/i/acct_test/invst_1234',
            'convenience_fee' => true
        ];
    }
}
