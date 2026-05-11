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
        $event = Events::factory()->create([
            'eventable_type' => Bookings::class,
            'eventable_id' => $booking->id,
            'date' => '2026-06-01',
            'start_time' => '19:00:00',
            'end_time' => '22:00:00',
            'venue_name' => 'Symphony Hall',
            'venue_address' => '1 Main St',
            'price' => 2500,  // dollars; Price cast multiplies by 100 on set() to store as cents
        ]);
        $event->refresh();
        // start_time/end_time are cast to Carbon (datetime:H:i), so equality
        // assertions format the Carbon instance to a string.
        $this->assertSame('19:00:00', $event->start_time->format('H:i:s'));
        $this->assertSame('22:00:00', $event->end_time->format('H:i:s'));
        $this->assertSame('Symphony Hall', $event->venue_name);
        $this->assertSame('1 Main St', $event->venue_address);
        $this->assertSame('2500.00', (string) $event->price);
    }
}
