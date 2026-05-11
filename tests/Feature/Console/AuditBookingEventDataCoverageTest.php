<?php

namespace Tests\Feature\Console;

use App\Models\Bands;
use App\Models\Bookings;
use App\Models\Events;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AuditBookingEventDataCoverageTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Bands $band;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->band = Bands::factory()->create();
    }

    private function makeBookingWithPrimaryEvent(array $bookingAttrs, ?array $eventAttrs): Bookings
    {
        // The audit's "primary event" data lives on the event, not the booking,
        // post-Chunk-1. Split incoming attrs accordingly: date/venue go to the
        // event; everything else stays on the booking.
        $movedFields = ['date', 'start_time', 'end_time', 'venue_name', 'venue_address'];
        $bookingOnlyAttrs = array_diff_key($bookingAttrs, array_flip($movedFields));
        $eventInheritedAttrs = array_intersect_key($bookingAttrs, array_flip($movedFields));

        $booking = Bookings::factory()->create(array_merge([
            'band_id' => $this->band->id,
            'author_id' => $this->user->id,
        ], $bookingOnlyAttrs));

        if ($eventAttrs !== null) {
            Events::factory()->create(array_merge([
                'eventable_type' => Bookings::class,
                'eventable_id' => $booking->id,
                'date' => $bookingAttrs['date'] ?? '2026-06-01',
            ], $eventInheritedAttrs, $eventAttrs));
        }

        return $booking;
    }

    public function test_reports_ok_when_primary_event_matches_booking(): void
    {
        $this->makeBookingWithPrimaryEvent(
            ['date' => '2026-06-01', 'venue_name' => 'The Hall', 'venue_address' => '1 Main St'],
            ['date' => '2026-06-01']
        );

        $this->artisan('bookings:audit-event-data-coverage')
            ->expectsOutputToContain('OK: 1')
            ->expectsOutputToContain('FLAGGED: 0')
            ->assertExitCode(0);
    }

    public function test_flags_booking_with_no_events(): void
    {
        $this->makeBookingWithPrimaryEvent(
            ['date' => '2026-06-01'],
            null
        );

        $this->artisan('bookings:audit-event-data-coverage')
            ->expectsOutputToContain('NO_EVENTS: 1')
            ->expectsOutputToContain('FLAGGED: 1')
            ->assertExitCode(1);
    }

    // Note: a date_mismatch unit test existed during Chunk 0 (pre-migration),
    // when bookings.date and events.date could diverge. Post-migration that
    // scenario is unconstructable through the model layer because bookings.date
    // is gone. The audit command's DATE_MISMATCH classifier branch remains
    // for the case where the command is re-run on a pre-migration dump, but
    // we no longer cover it via PHPUnit.

    public function test_picks_primary_event_by_chronological_order(): void
    {
        $booking = $this->makeBookingWithPrimaryEvent(
            ['date' => '2026-06-01'],
            ['date' => '2026-06-15']
        );
        // Add an earlier event; this should now be the primary.
        Events::factory()->create([
            'eventable_type' => Bookings::class,
            'eventable_id' => $booking->id,
            'date' => '2026-06-01',
        ]);

        $this->artisan('bookings:audit-event-data-coverage')
            ->expectsOutputToContain('OK: 1')
            ->expectsOutputToContain('MULTI_EVENT: 1')
            ->assertExitCode(0);
    }

    public function test_multi_event_count_is_informational_not_flagged(): void
    {
        $booking = $this->makeBookingWithPrimaryEvent(
            ['date' => '2026-06-01'],
            ['date' => '2026-06-01']
        );
        Events::factory()->create([
            'eventable_type' => Bookings::class,
            'eventable_id' => $booking->id,
            'date' => '2026-06-15',
        ]);

        $this->artisan('bookings:audit-event-data-coverage')
            ->expectsOutputToContain('OK: 1')
            ->expectsOutputToContain('MULTI_EVENT: 1')
            ->expectsOutputToContain('FLAGGED: 0')
            ->assertExitCode(0);
    }

    public function test_csv_option_writes_file(): void
    {
        // Use a NO_EVENTS booking to drive the FLAGGED path post-migration.
        $this->makeBookingWithPrimaryEvent(
            ['name' => 'Sample Booking'],
            null
        );

        $path = storage_path('app/test-audit.csv');
        if (file_exists($path)) {
            unlink($path);
        }

        $this->artisan('bookings:audit-event-data-coverage', ['--csv' => $path])
            ->assertExitCode(1);

        $this->assertFileExists($path);
        $contents = file_get_contents($path);
        $this->assertStringContainsString('booking_id,band_id,category', $contents);
        $this->assertStringContainsString('NO_EVENTS', $contents);

        unlink($path);
    }

    public function test_csv_handles_booking_with_no_events_without_crashing(): void
    {
        // Regression test for null-dereference bug: prior to the fix, writing
        // a NO_EVENTS booking row to CSV crashed with "Attempt to read property
        // on null" under PHP 8.1+ because $primary is null.
        $this->makeBookingWithPrimaryEvent(
            ['date' => '2026-06-01'],
            null
        );

        $path = storage_path('app/test-audit-no-events.csv');
        if (file_exists($path)) {
            unlink($path);
        }

        $this->artisan('bookings:audit-event-data-coverage', ['--csv' => $path])
            ->assertExitCode(1);

        $this->assertFileExists($path);
        $contents = file_get_contents($path);
        $this->assertStringContainsString('NO_EVENTS', $contents);

        unlink($path);
    }
}
