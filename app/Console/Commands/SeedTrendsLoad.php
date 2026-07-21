<?php

namespace App\Console\Commands;

use App\Enums\PaymentType;
use App\Models\Bands;
use App\Models\Bookings;
use App\Models\Events;
use App\Models\Payments;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Bulk-load bookings + events + payments so the mobile finances/trends endpoint
 * has a realistic-plus-worst-case dataset to profile against.
 *
 * The trends endpoint fans out per booking (getStartDate hits the events table,
 * scopePaid/scopeUnpaid subquery hits the payments table), so we need volume in
 * all three tables to see the query plan under load.
 */
class SeedTrendsLoad extends Command
{
    protected $signature = 'dev:seed-trends
                            {--band=test_band : site_name of the band to load data onto}
                            {--bookings=5000 : how many bookings to create}
                            {--years=6 : spread bookings across the past N years}
                            {--chunk=500 : insert chunk size}
                            {--fresh : wipe existing bookings/events/payments for this band first}';

    protected $description = 'Bulk-seed bookings, events, and payments for stress-testing the finances trends endpoint';

    public function handle(): int
    {
        $siteName = (string) $this->option('band');
        $bookingCount = (int) $this->option('bookings');
        $years = max(1, (int) $this->option('years'));
        $chunk = max(50, (int) $this->option('chunk'));

        $band = Bands::where('site_name', $siteName)->first();
        if (!$band) {
            $this->error("Band with site_name={$siteName} not found. Run `php artisan dev:setup --user --band` first.");
            return self::FAILURE;
        }

        $author = User::whereIn('id', $band->owners()->pluck('user_id'))->first()
            ?? User::first();
        if (!$author) {
            $this->error('No user found to attribute bookings to.');
            return self::FAILURE;
        }

        if ($this->option('fresh')) {
            $this->info("Wiping existing bookings/events/payments for band #{$band->id}...");
            $existingBookingIds = Bookings::where('band_id', $band->id)->pluck('id');
            Payments::where('band_id', $band->id)
                ->where('payable_type', Bookings::class)
                ->delete();
            Events::whereIn('eventable_id', $existingBookingIds)
                ->where('eventable_type', Bookings::class)
                ->delete();
            Bookings::whereIn('id', $existingBookingIds)->delete();
        }

        $startWindow = now()->subYears($years);
        $endWindow = now()->addYear();
        $secondsSpan = $endWindow->getTimestamp() - $startWindow->getTimestamp();

        $paymentTypes = array_map(fn ($c) => $c->value, PaymentType::cases());
        $statuses = ['confirmed', 'confirmed', 'confirmed', 'pending', 'cancelled'];

        $this->info(sprintf(
            'Seeding %d bookings (+events, +0-2 payments each) for %s across %d years...',
            $bookingCount,
            $band->name,
            $years
        ));

        $bar = $this->output->createProgressBar($bookingCount);
        $bar->start();

        $now = now();
        $bookingsBuffer = [];
        $totalCreated = 0;

        // Insert bookings in chunks so we get back auto-increment IDs to build
        // matching events + payments rows without a round trip per row.
        $remaining = $bookingCount;
        while ($remaining > 0) {
            $batch = min($chunk, $remaining);
            $bookingsBuffer = [];

            for ($i = 0; $i < $batch; $i++) {
                $offsetSeconds = random_int(0, $secondsSpan);
                $createdAt = Carbon::createFromTimestamp($startWindow->getTimestamp() + $offsetSeconds);
                $priceCents = random_int(50_000, 1_500_000); // $500 - $15,000
                $status = $statuses[array_rand($statuses)];

                $bookingsBuffer[] = [
                    'band_id' => $band->id,
                    'author_id' => $author->id,
                    'name' => 'Load Test Booking ' . Str::random(8),
                    'event_type_id' => random_int(1, 6),
                    'price' => $priceCents,
                    'deposit_type' => 'percent',
                    'deposit_value' => 50,
                    'status' => $status,
                    'contract_option' => 'default',
                    'notes' => null,
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ];
            }

            DB::transaction(function () use ($bookingsBuffer, $band, $paymentTypes, $now, $bar) {
                // Bulk insert bookings, then read back the new IDs. Bookings has
                // an auto-increment PK and we inserted contiguously.
                $firstIdBefore = (int) DB::table('bookings')->max('id');
                DB::table('bookings')->insert($bookingsBuffer);
                $inserted = DB::table('bookings')
                    ->where('id', '>', $firstIdBefore)
                    ->where('band_id', $band->id)
                    ->orderBy('id')
                    ->get(['id', 'price', 'status', 'event_type_id', 'created_at']);

                $eventsBuffer = [];
                $paymentsBuffer = [];

                foreach ($inserted as $booking) {
                    // Give each booking one event scheduled within roughly the
                    // same year window it was created in, so it lands in a
                    // predictable month bucket.
                    $eventDate = Carbon::parse($booking->created_at)
                        ->addDays(random_int(-30, 365));

                    $eventsBuffer[] = [
                        'eventable_id' => $booking->id,
                        'eventable_type' => Bookings::class,
                        'event_type_id' => $booking->event_type_id,
                        'key' => (string) Str::uuid(),
                        'title' => 'Load Test Event',
                        'date' => $eventDate->format('Y-m-d'),
                        'start_time' => '19:00:00',
                        'end_time' => '22:00:00',
                        'venue_name' => 'Load Test Venue',
                        'venue_address' => '123 Nowhere St, City, ST 00000',
                        'additional_data' => json_encode(['seed' => 'trends-load']),
                        'created_at' => $booking->created_at,
                        'updated_at' => $booking->created_at,
                    ];

                    // Skip payments entirely for cancelled bookings (matches
                    // the trends bucket which ignores them anyway) and for a
                    // random slice of bookings so unpaid/partial/paid mix.
                    if ($booking->status === 'cancelled') {
                        continue;
                    }

                    $priceCents = (int) $booking->price;
                    $roll = random_int(1, 100);
                    // ~35% fully paid (2 payments), ~35% deposit only, ~30% unpaid
                    if ($roll <= 35) {
                        // Deposit + balance
                        $deposit = (int) floor($priceCents * 0.5);
                        $balance = $priceCents - $deposit;
                        $paymentsBuffer[] = $this->paymentRow($booking, $band, $deposit, $paymentTypes, 7);
                        $paymentsBuffer[] = $this->paymentRow($booking, $band, $balance, $paymentTypes, 21);
                    } elseif ($roll <= 70) {
                        // Deposit only
                        $deposit = (int) floor($priceCents * 0.5);
                        $paymentsBuffer[] = $this->paymentRow($booking, $band, $deposit, $paymentTypes, 7);
                    }
                }

                if (!empty($eventsBuffer)) {
                    foreach (array_chunk($eventsBuffer, 500) as $slice) {
                        DB::table('events')->insert($slice);
                    }
                }
                if (!empty($paymentsBuffer)) {
                    foreach (array_chunk($paymentsBuffer, 500) as $slice) {
                        DB::table('payments')->insert($slice);
                    }
                }

                $bar->advance(count($inserted));
            });

            $totalCreated += $batch;
            $remaining -= $batch;
        }

        $bar->finish();
        $this->newLine();

        $this->info(sprintf(
            'Done. Bookings for band #%d: %d | Events: %d | Payments: %d',
            $band->id,
            Bookings::where('band_id', $band->id)->count(),
            Events::where('eventable_type', Bookings::class)
                ->whereIn('eventable_id', Bookings::where('band_id', $band->id)->pluck('id'))
                ->count(),
            Payments::where('band_id', $band->id)
                ->where('payable_type', Bookings::class)
                ->count(),
        ));

        return self::SUCCESS;
    }

    private function paymentRow($booking, Bands $band, int $amountCents, array $paymentTypes, int $daysAfter): array
    {
        $date = Carbon::parse($booking->created_at)->addDays($daysAfter);
        return [
            'band_id' => $band->id,
            'payable_type' => Bookings::class,
            'payable_id' => $booking->id,
            'name' => 'Load Test Payment',
            'amount' => $amountCents,
            'date' => $date->format('Y-m-d'),
            'status' => 'paid',
            'payment_type' => $paymentTypes[array_rand($paymentTypes)],
            'created_at' => $date,
            'updated_at' => $date,
        ];
    }
}
