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
        // Re-enable in Task 3 once Bookings factory drops the moved fillables
        // and Events factory supports the new ones. Will assert that the
        // new event columns (start_time, end_time, venue_name, venue_address,
        // price) round-trip values correctly.
        $this->markTestSkipped('Pending Task 3: Bookings factory + Events fillable/casts update required.');
    }
}
