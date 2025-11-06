<?php

namespace Database\Seeders;

use App\Models\Bands;
use App\Models\Bookings;
use App\Models\Payments;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PaymentsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Starting to seed bookings and payments...');
        
        // Find the existing test band or create one
        $band = Bands::where('name', 'Test Band')->first();
        
        if (!$band) {
            $this->command->error('Test Band not found. Please run DevSetupSeeder first.');
            return;
        }

        $user = $band->owners->first()->user;
        
        // Generate bookings and payments for multiple years (2020-2025)
        $years = [2020, 2021, 2022, 2023, 2024, 2025];
        $totalBookings = 0;
        $totalPayments = 0;
        
        foreach ($years as $year) {
            // Create 15-25 bookings per year
            $bookingsCount = rand(15, 25);
            
            for ($i = 0; $i < $bookingsCount; $i++) {
                // Generate random date within the year
                $month = rand(1, 12);
                $day = rand(1, 28);
                $date = Carbon::create($year, $month, $day);
                
                // For past dates, make some confirmed, for future dates make some pending
                $isPast = $date->isPast();
                $status = $isPast 
                    ? (rand(1, 10) > 2 ? 'confirmed' : 'cancelled') // 80% confirmed for past
                    : (rand(1, 10) > 5 ? 'confirmed' : 'pending'); // 50% confirmed for future
                
                $startTime = sprintf('%02d:%02d', rand(17, 22), rand(0, 59));
                $endTime = sprintf('%02d:%02d', rand(23, 23), rand(0, 59));
                
                $eventTypes = [
                    1 => ['Wedding', 'Reception'],
                    2 => ['Corporate Event', 'Company Party', 'Holiday Party'],
                    3 => ['Private Party', 'Birthday Party', 'Anniversary'],
                    4 => ['Festival', 'Community Event'],
                    5 => ['Bar Gig', 'Restaurant Gig', 'Club Night'],
                    6 => ['Concert', 'Showcase'],
                ];
                
                $eventTypeId = array_rand($eventTypes);
                $eventNames = $eventTypes[$eventTypeId];
                $eventName = $eventNames[array_rand($eventNames)];
                
                // Price ranges based on event type
                $priceRanges = [
                    1 => [8000, 15000],  // Weddings
                    2 => [5000, 12000],  // Corporate
                    3 => [3000, 8000],   // Private
                    4 => [4000, 10000],  // Festival
                    5 => [1500, 4000],   // Bar/Restaurant
                    6 => [2000, 6000],   // Concert
                ];
                
                $priceRange = $priceRanges[$eventTypeId];
                $price = rand($priceRange[0], $priceRange[1]);
                
                $venues = [
                    'The Grand Ballroom', 'City Convention Center', 'Riverside Gardens',
                    'Magnolia Hall', 'The Jazz Cafe', 'Blues Bar & Grill', 
                    'Downtown Event Center', 'Lakeside Pavilion', 'The Music Hall',
                    'Bourbon Street Club', 'Vintage Theater', 'Garden District Venue'
                ];
                
                $booking = Bookings::create([
                    'band_id' => $band->id,
                    'author_id' => $user->id,
                    'name' => $eventName . ' at ' . $venues[array_rand($venues)],
                    'event_type_id' => $eventTypeId,
                    'date' => $date->format('Y-m-d'),
                    'start_time' => $startTime,
                    'end_time' => $endTime,
                    'venue_name' => $venues[array_rand($venues)],
                    'venue_address' => rand(100, 9999) . ' ' . ['Main St', 'Bourbon St', 'Canal St', 'Magazine St', 'Frenchmen St'][array_rand(['Main St', 'Bourbon St', 'Canal St', 'Magazine St', 'Frenchmen St'])] . ', New Orleans, LA',
                    'price' => $price,
                    'status' => $status,
                    'contract_option' => 'default',
                    'notes' => null,
                ]);
                
                $totalBookings++;
                
                // Add payments for confirmed bookings (more likely for past events)
                if ($status === 'confirmed') {
                    // Determine if this booking has been paid
                    $isPaid = $isPast 
                        ? (rand(1, 10) > 2) // 80% of past bookings are paid
                        : (rand(1, 10) > 7); // 30% of future bookings have deposits
                    
                    if ($isPaid) {
                        // Determine number of payments (1-3)
                        $numPayments = rand(1, 3);
                        $totalPaid = 0;
                        
                        for ($p = 0; $p < $numPayments; $p++) {
                            if ($p === 0 && $numPayments > 1) {
                                // First payment is deposit (30-50% of total)
                                $paymentAmount = round($price * rand(30, 50) / 100);
                                $paymentName = 'Deposit';
                                $paymentDate = $date->copy()->subDays(rand(30, 60));
                            } elseif ($p === $numPayments - 1) {
                                // Final payment is remainder
                                $paymentAmount = $price - $totalPaid;
                                $paymentName = 'Final Payment';
                                $paymentDate = $isPast 
                                    ? $date->copy()->subDays(rand(1, 7))
                                    : $date->copy()->subDays(rand(1, 3));
                            } else {
                                // Middle payment
                                $remaining = $price - $totalPaid;
                                $paymentAmount = round($remaining * rand(40, 60) / 100);
                                $paymentName = 'Payment ' . ($p + 1);
                                $paymentDate = $date->copy()->subDays(rand(15, 45));
                            }
                            
                            // Only create payment if it's in the past or very recent
                            if ($paymentDate->isPast() || $paymentDate->diffInDays(now()) < 7) {
                                Payments::create([
                                    'name' => $paymentName,
                                    'payable_type' => Bookings::class,
                                    'payable_id' => $booking->id,
                                    'amount' => $paymentAmount,
                                    'date' => $paymentDate->format('Y-m-d'),
                                    'band_id' => $band->id,
                                    'user_id' => $user->id,
                                    'status' => 'completed',
                                ]);
                                
                                $totalPaid += $paymentAmount;
                                $totalPayments++;
                            }
                        }
                    }
                }
            }
            
            $this->command->info("Created $bookingsCount bookings for $year");
        }
        
        $this->command->info("✓ Total bookings created: $totalBookings");
        $this->command->info("✓ Total payments created: $totalPayments");
        $this->command->info("✓ Revenue data ready for visualization!");
    }
}
