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

        // The audit command counts global table state, so any residual rows
        // from prior tests in the suite would corrupt our counts. RefreshDatabase
        // should make this unnecessary, but other tests in the suite leave bookings
        // behind that survive the transactional rollback. Explicitly clear the
        // relevant tables so each audit test starts with a guaranteed clean slate.
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::table('events')->truncate();
        DB::table('bookings')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        $this->user = User::factory()->create();
        $this->band = Bands::factory()->create();
    }

    private function makeBookingWithPrimaryEvent(array $bookingAttrs, ?array $eventAttrs): Bookings
    {
        $booking = Bookings::factory()->create(array_merge([
            'band_id' => $this->band->id,
            'author_id' => $this->user->id,
        ], $bookingAttrs));

        if ($eventAttrs !== null) {
            Events::factory()->create(array_merge([
                'eventable_type' => Bookings::class,
                'eventable_id' => $booking->id,
                'date' => $bookingAttrs['date'] ?? '2026-06-01',
            ], $eventAttrs));
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

    public function test_flags_booking_with_date_mismatch(): void
    {
        $this->makeBookingWithPrimaryEvent(
            ['date' => '2026-06-01'],
            ['date' => '2026-06-15']
        );

        $this->artisan('bookings:audit-event-data-coverage')
            ->expectsOutputToContain('DATE_MISMATCH: 1')
            ->expectsOutputToContain('FLAGGED: 1')
            ->assertExitCode(1);
    }

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
        $this->makeBookingWithPrimaryEvent(
            ['date' => '2026-06-01'],
            ['date' => '2026-06-15']
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
        $this->assertStringContainsString('DATE_MISMATCH', $contents);

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
