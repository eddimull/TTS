<?php

namespace Tests\Feature\Migrations;

use App\Models\Bands;
use App\Models\Bookings;
use App\Models\Events;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MoveDateVenueFromBookingsToEventsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Bands $band;

    protected function setUp(): void
    {
        parent::setUp();

        // RefreshDatabase + this test class's own data only; no leakage.
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::table('events')->truncate();
        DB::table('bookings')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        $this->user = User::factory()->create();
        $this->band = Bands::factory()->create();
    }

    public function test_post_migration_events_table_has_new_columns(): void
    {
        $this->assertTrue(Schema::hasColumn('events', 'start_time'));
        $this->assertTrue(Schema::hasColumn('events', 'end_time'));
        $this->assertTrue(Schema::hasColumn('events', 'venue_name'));
        $this->assertTrue(Schema::hasColumn('events', 'venue_address'));
        $this->assertTrue(Schema::hasColumn('events', 'price'));
    }

    public function test_post_migration_events_table_drops_legacy_time_column(): void
    {
        // The legacy events.time column is consolidated into events.start_time
        // by the migration's UPDATE statement, then dropped.
        $this->assertFalse(Schema::hasColumn('events', 'time'));
    }

    public function test_post_migration_bookings_table_has_dropped_columns(): void
    {
        $this->assertFalse(Schema::hasColumn('bookings', 'date'));
        $this->assertFalse(Schema::hasColumn('bookings', 'start_time'));
        $this->assertFalse(Schema::hasColumn('bookings', 'end_time'));
        $this->assertFalse(Schema::hasColumn('bookings', 'venue_name'));
        $this->assertFalse(Schema::hasColumn('bookings', 'venue_address'));
    }

    public function test_event_columns_can_hold_expected_values(): void
    {
        $booking = Bookings::factory()->create([
            'band_id' => $this->band->id,
            'author_id' => $this->user->id,
        ]);
        $event = Events::factory()->create([
            'eventable_type' => Bookings::class,
            'eventable_id' => $booking->id,
            'date' => '2026-06-01',
            'start_time' => '19:00:00',
            'end_time' => '22:00:00',
            'venue_name' => 'Symphony Hall',
            'venue_address' => '1 Main St',
            'price' => 250000,  // cents, matches Price cast convention
        ]);
        $event->refresh();
        $this->assertSame('19:00:00', $event->start_time);
        $this->assertSame('22:00:00', $event->end_time);
        $this->assertSame('Symphony Hall', $event->venue_name);
        $this->assertSame('1 Main St', $event->venue_address);
        $this->assertSame('2500.00', (string) $event->price);
    }
}
