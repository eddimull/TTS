<?php

namespace Database\Seeders;

use App\Models\Bands;
use App\Models\StripeAccounts;
use Illuminate\Database\Seeder;

class StripeTestSeeder extends Seeder
{
    /**
     * Seed Stripe test accounts for development
     * 
     * This seeder creates actual Stripe Connect accounts for bands
     * so that the contact portal payment checkout can work in development.
     * 
     * Uses Stripe test mode to create real connected accounts that can
     * accept test payments with test cards.
     *
     * @return void
     */
    public function run()
    {
        // Get all bands that don't have a Stripe account yet
        $bands = Bands::whereDoesntHave('stripeAccount')->get();

        if ($bands->isEmpty()) {
            $this->command->info('All bands already have Stripe accounts.');
            return;
        }

        // Initialize Stripe with the API key
        \Stripe\Stripe::setApiKey(config('services.stripe.key'));

        foreach ($bands as $band) {
            try {
                // Create a real Stripe Connect account in test mode
                $account = \Stripe\Account::create([
                    'type' => 'express', // Using express for easier test setup
                    'country' => 'US',
                    'email' => $band->email ?? 'test+' . $band->id . '@example.com',
                    'capabilities' => [
                        'card_payments' => ['requested' => true],
                        'transfers' => ['requested' => true],
                    ],
                ]);

                // Store the account in the database
                StripeAccounts::create([
                    'band_id' => $band->id,
                    'stripe_account_id' => $account->id,
                    'status' => 'active', // Mark as active for testing
                ]);

                $this->command->info("✓ Created Stripe Connect account for band: {$band->name} ({$account->id})");
            } catch (\Stripe\Exception\ApiErrorException $e) {
                $this->command->error("✗ Failed to create Stripe account for band {$band->name}: {$e->getMessage()}");
            }
        }

        $this->command->info('');
        $this->command->info('Stripe Connect accounts created successfully!');
        $this->command->info('📝 Note: These are real test mode accounts that can accept test payments.');
        $this->command->info('💳 Use Stripe test cards: https://stripe.com/docs/testing');
    }
}
