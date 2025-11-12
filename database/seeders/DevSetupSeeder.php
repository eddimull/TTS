<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Bands;
use App\Models\Bookings;
use App\Models\Proposals;
use App\Models\BandEvents;
use App\Models\BandOwners;
use App\Models\Payments;
use App\Models\StripeAccounts;
use Illuminate\Database\Seeder;
use App\Models\RehearsalSchedule;

class DevSetupSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Seed event types first if they don't exist
        if (\App\Models\EventTypes::count() === 0) {
            $this->call(EventTypeSeeder::class);
        }

        $user = User::create([
            'name' => 'Admin',
            'email'=>'admin@example.com',
            'password'=>'$2y$10$9qoA9D9VwXtszzBAF/D4aetJNzpbVI8/5fTtFm.RktK9lCKGSbNcq' // password
        ]);
        $this->command->info('Admin user (admin@example.com) created with password "password"');

        $band = Bands::create([
            'name' => 'Test Band',
            'site_name' => 'test_band'
        ]);
        BandOwners::create([
            'user_id'=>$user->id,
            'band_id'=>$band->id
        ]);

        // Create actual Stripe Connect account for the band
        try {
            \Stripe\Stripe::setApiKey(config('services.stripe.key'));
            
            $account = \Stripe\Account::create([
                'type' => 'express',
                'country' => 'US',
                'email' => 'testband+' . $band->id . '@example.com',
                'capabilities' => [
                    'card_payments' => ['requested' => true],
                    'transfers' => ['requested' => true],
                ],
            ]);

            StripeAccounts::create([
                'band_id' => $band->id,
                'stripe_account_id' => $account->id,
                'status' => 'active',
            ]);
            
            $this->command->info("✓ Created Stripe Connect account for Test Band ({$account->id})");
        } catch (\Stripe\Exception\ApiErrorException $e) {
            $this->command->warn("⚠ Failed to create Stripe account: {$e->getMessage()}");
            $this->command->warn("Continuing without Stripe account...");
        }

        // Create bookings with varied created_at dates for time travel testing
        $allBookings = [];

        // 3 bookings from 12 months ago (2 paid, 1 unpaid)
        for ($i = 0; $i < 3; $i++) {
            $booking = Bookings::factory()->create([
                'band_id' => $band->id,
                'author_id' => $user->id,
                'created_at' => now()->subMonths(12)->addDays($i * 5),
                'updated_at' => now()->subMonths(12)->addDays($i * 5),
            ]);
            $allBookings[] = ['booking' => $booking, 'paid' => $i < 2];
        }

        // 3 bookings from 6 months ago (2 paid, 1 unpaid)
        for ($i = 0; $i < 3; $i++) {
            $booking = Bookings::factory()->create([
                'band_id' => $band->id,
                'author_id' => $user->id,
                'created_at' => now()->subMonths(6)->addDays($i * 5),
                'updated_at' => now()->subMonths(6)->addDays($i * 5),
            ]);
            $allBookings[] = ['booking' => $booking, 'paid' => $i < 2];
        }

        // 2 bookings from 3 months ago (1 paid, 1 unpaid)
        for ($i = 0; $i < 2; $i++) {
            $booking = Bookings::factory()->create([
                'band_id' => $band->id,
                'author_id' => $user->id,
                'created_at' => now()->subMonths(3)->addDays($i * 5),
                'updated_at' => now()->subMonths(3)->addDays($i * 5),
            ]);
            $allBookings[] = ['booking' => $booking, 'paid' => $i < 1];
        }

        // 2 bookings from 1 month ago (1 paid, 1 unpaid)
        for ($i = 0; $i < 2; $i++) {
            $booking = Bookings::factory()->create([
                'band_id' => $band->id,
                'author_id' => $user->id,
                'created_at' => now()->subMonth()->addDays($i * 5),
                'updated_at' => now()->subMonth()->addDays($i * 5),
            ]);
            $allBookings[] = ['booking' => $booking, 'paid' => $i < 1];
        }

        // 5 bookings from the past 2 weeks (recent - mix of paid/unpaid)
        for ($i = 0; $i < 5; $i++) {
            $booking = Bookings::factory()->create([
                'band_id' => $band->id,
                'author_id' => $user->id,
                'created_at' => now()->subDays($i * 2),
                'updated_at' => now()->subDays($i * 2),
            ]);
            $allBookings[] = ['booking' => $booking, 'paid' => $i < 2];
        }

        // Add payments to the paid bookings
        foreach ($allBookings as $item) {
            if ($item['paid']) {
                Payments::create([
                    'band_id' => $band->id,
                    'payable_type' => Bookings::class,
                    'payable_id' => $item['booking']->id,
                    'amount' => $item['booking']->price,
                    'status' => 'paid',
                    'name' => 'Full Payment',
                    'date' => $item['booking']->created_at->addDays(7),
                    'created_at' => $item['booking']->created_at->addDays(7),
                    'updated_at' => $item['booking']->created_at->addDays(7),
                ]);
            }
        }

        $this->command->info('Created Test Band with 15 bookings spread over the past year (8 paid, 7 unpaid)');

        // Create rehearsal schedules
        RehearsalSchedule::create([
            'band_id' => $band->id,
            'name' => 'Weekly Practice',
            'description' => 'Regular weekly practice sessions',
            'frequency' => 'weekly',
            'day_of_week' => 'tuesday',
            'default_time' => '19:00:00',
            'location_name' => 'Band Practice Space',
            'location_address' => '123 Music St, New Orleans, LA 70115',
            'notes' => 'Bring your gear and be ready to rock!',
            'active' => true,
        ]);

        RehearsalSchedule::create([
            'band_id' => $band->id,
            'name' => 'Thursday Jam',
            'description' => 'Casual jam sessions',
            'frequency' => 'weekly',
            'day_of_week' => 'thursday',
            'default_time' => '20:00:00',
            'location_name' => 'Studio B',
            'location_address' => '456 Jazz Ave, New Orleans, LA 70116',
            'active' => true,
        ]);

        $this->command->info('Created 2 rehearsal schedules for Test Band');

    }
}
