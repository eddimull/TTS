<?php

namespace Tests\Feature\Models;

use App\Models\Bands;
use App\Models\Bookings;
use App\Models\Events;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BookingsDerivedAccessorsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Bands $band;

    protected function setUp(): void
    {
        parent::setUp();
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::table('events')->truncate();
        DB::table('bookings')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
        $this->user = User::factory()->create();
        $this->band = Bands::factory()->create();
    }

    private function makeBookingWithEvents(array $eventDates, array $venues = []): Bookings
    {
        // Use DB::table() insert to bypass the factory's Model::unguarded() call,
        // which would try to insert the now-dropped columns into the bookings table.
        $now = now()->toDateTimeString();
        $bookingId = DB::table('bookings')->insertGetId([
            'band_id' => $this->band->id,
            'author_id' => $this->user->id,
            'name' => 'Test Booking',
            'event_type_id' => 1,
            'price' => 100000,
            'status' => 'confirmed',
            'contract_option' => 'default',
            'enable_portal_media_access' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $booking = Bookings::find($bookingId);
        foreach ($eventDates as $i => $date) {
            Events::factory()->create([
                'eventable_type' => Bookings::class,
                'eventable_id' => $booking->id,
                'date' => $date,
                'start_time' => '19:00:00',
                'end_time' => '22:00:00',
                'venue_name' => $venues[$i] ?? null,
            ]);
        }
        return $booking->refresh();
    }

    public function test_start_date_is_min_event_date(): void
    {
        $b = $this->makeBookingWithEvents(['2026-06-15', '2026-06-13', '2026-06-17']);
        $this->assertSame('2026-06-13', $b->start_date->toDateString());
    }

    public function test_end_date_is_max_event_date(): void
    {
        $b = $this->makeBookingWithEvents(['2026-06-15', '2026-06-13', '2026-06-17']);
        $this->assertSame('2026-06-17', $b->end_date->toDateString());
    }

    public function test_event_count_returns_count(): void
    {
        $b = $this->makeBookingWithEvents(['2026-06-13', '2026-06-15']);
        $this->assertSame(2, $b->event_count);
    }

    public function test_is_multi_event_true_when_multiple_events(): void
    {
        $b = $this->makeBookingWithEvents(['2026-06-13', '2026-06-15']);
        $this->assertTrue($b->is_multi_event);
    }

    public function test_is_multi_event_false_when_single_event(): void
    {
        $b = $this->makeBookingWithEvents(['2026-06-13']);
        $this->assertFalse($b->is_multi_event);
    }

    public function test_venue_summary_single_when_all_match(): void
    {
        $b = $this->makeBookingWithEvents(
            ['2026-06-13', '2026-06-15'],
            ['Symphony Hall', 'Symphony Hall']
        );
        $this->assertSame('Symphony Hall', $b->venue_summary);
    }

    public function test_venue_summary_multiple_when_mixed(): void
    {
        $b = $this->makeBookingWithEvents(
            ['2026-06-13', '2026-06-15'],
            ['Symphony Hall', 'Town Hall']
        );
        $this->assertSame('Multiple venues', $b->venue_summary);
    }

    public function test_venue_summary_null_when_none_set(): void
    {
        $b = $this->makeBookingWithEvents(['2026-06-13', '2026-06-15']);
        $this->assertNull($b->venue_summary);
    }

    public function test_total_duration_sums_event_durations(): void
    {
        $b = $this->makeBookingWithEvents(['2026-06-13', '2026-06-15']);
        // 19:00 → 22:00 = 3 hours each, total 6.
        $this->assertEqualsWithDelta(6.0, $b->total_duration, 0.01);
    }

    public function test_legacy_date_attribute_no_longer_present(): void
    {
        $b = $this->makeBookingWithEvents(['2026-06-13']);
        // After the hard switch, $booking->date should not return anything
        // useful. The exact failure mode is acceptable as long as it's not
        // a silent wrong answer. Acceptable: null.
        $this->assertNull($b->getAttribute('date'));
    }
}
