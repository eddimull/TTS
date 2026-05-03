<?php

namespace App\Console\Commands;

use App\Models\Bookings;
use App\Models\Events;
use Illuminate\Console\Command;

class AuditBookingEventDataCoverage extends Command
{
    protected $signature = 'bookings:audit-event-data-coverage
                            {--csv= : Optional path to write a per-booking CSV report}';

    protected $description = 'Audit each booking to verify its primary event will losslessly receive booking-level date/venue data during the Chunk 1 migration.';

    private const CAT_OK = 'OK';
    private const CAT_NO_EVENTS = 'NO_EVENTS';
    private const CAT_DATE_MISMATCH = 'DATE_MISMATCH';
    private const CAT_VENUE_NAME_MISMATCH = 'VENUE_NAME_MISMATCH';
    private const CAT_VENUE_ADDRESS_MISMATCH = 'VENUE_ADDRESS_MISMATCH';

    public function handle(): int
    {
        $csvPath = $this->option('csv');
        $csvHandle = null;
        if ($csvPath) {
            $csvHandle = @fopen($csvPath, 'w');
            if ($csvHandle === false) {
                $this->error("Could not open CSV path for writing: {$csvPath}");
                return self::FAILURE;
            }
            fputcsv($csvHandle, [
                'booking_id', 'band_id', 'category', 'multi_event',
                'booking_date', 'primary_event_date',
                'booking_venue_name', 'primary_event_venue_name',
                'booking_venue_address', 'primary_event_venue_address',
                'event_count',
            ]);
        }

        $counts = [
            self::CAT_OK => 0,
            self::CAT_NO_EVENTS => 0,
            self::CAT_DATE_MISMATCH => 0,
            self::CAT_VENUE_NAME_MISMATCH => 0,
            self::CAT_VENUE_ADDRESS_MISMATCH => 0,
        ];
        $multiEventCount = 0;
        $flagged = 0;
        $total = 0;

        Bookings::with(['events' => function ($q) {
            $q->orderBy('date')->orderBy('id');
        }])->chunk(200, function ($bookings) use (&$counts, &$multiEventCount, &$flagged, &$total, $csvHandle) {
            foreach ($bookings as $booking) {
                $total++;
                $events = $booking->events;
                $eventCount = $events->count();
                $isMulti = $eventCount > 1;
                if ($isMulti) {
                    $multiEventCount++;
                }

                if ($eventCount === 0) {
                    $category = self::CAT_NO_EVENTS;
                    $primary = null;
                } else {
                    $primary = $events->first();
                    $category = $this->classify($booking, $primary);
                }

                $counts[$category]++;
                if ($category !== self::CAT_OK) {
                    $flagged++;
                }

                if ($csvHandle) {
                    fputcsv($csvHandle, [
                        $booking->id,
                        $booking->band_id,
                        $category,
                        $isMulti ? '1' : '0',
                        $booking->date ? $booking->date->toDateString() : '',
                        $primary?->date?->toDateString() ?? '',
                        $booking->venue_name ?? '',
                        $primary?->venue_name ?? '',
                        $booking->venue_address ?? '',
                        $primary?->venue_address ?? '',
                        $eventCount,
                    ]);
                }
            }
        });

        if ($csvHandle) {
            fclose($csvHandle);
        }

        $this->newLine();
        $this->info("=== Booking Event Data Coverage Audit ===");
        $this->line("Total bookings: {$total}");
        foreach ($counts as $category => $count) {
            $this->line("  {$category}: {$count}");
        }
        $this->line("MULTI_EVENT: {$multiEventCount} (informational; not flagged)");
        $this->line("FLAGGED: {$flagged}");

        if ($csvPath) {
            $this->line("CSV written to: {$csvPath}");
        }

        return $flagged > 0 ? 1 : 0;
    }

    private function classify(Bookings $booking, Events $primary): string
    {
        $bookingDate = $booking->date ? $booking->date->toDateString() : null;
        $primaryDate = $primary->date ? $primary->date->toDateString() : null;

        if ($bookingDate && $primaryDate && $bookingDate !== $primaryDate) {
            return self::CAT_DATE_MISMATCH;
        }

        if ($this->nonEmptyMismatch($booking->venue_name, $primary->venue_name ?? null)) {
            return self::CAT_VENUE_NAME_MISMATCH;
        }

        if ($this->nonEmptyMismatch($booking->venue_address, $primary->venue_address ?? null)) {
            return self::CAT_VENUE_ADDRESS_MISMATCH;
        }

        return self::CAT_OK;
    }

    private function nonEmptyMismatch(?string $a, ?string $b): bool
    {
        $aNorm = $a !== null ? strtolower(trim($a)) : '';
        $bNorm = $b !== null ? strtolower(trim($b)) : '';
        if ($aNorm === '' || $bNorm === '') {
            return false;
        }
        return $aNorm !== $bNorm;
    }
}
