<?php

use App\Models\Bookings;
use App\Models\Events;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        // Step 1: Add new columns to events.
        Schema::table('events', function (Blueprint $table) {
            $table->time('start_time')->nullable()->after('date');
            $table->time('end_time')->nullable()->after('start_time');
            $table->string('venue_name')->nullable()->after('end_time');
            $table->string('venue_address')->nullable()->after('venue_name');
            $table->integer('price')->nullable()->after('venue_address');  // cents, matches Price cast convention
        });

        // Step 1a: Consolidate the legacy events.time column into start_time.
        // events.time has historically been the single time field used by
        // rehearsals, band events, and booking-derived events. The redesign
        // standardizes on start_time/end_time. Copy all non-null time values
        // into the new start_time column before any other backfill so booking
        // code paths that read both columns see consistent values.
        DB::statement('UPDATE events SET start_time = time WHERE time IS NOT NULL');

        // Steps 2 and 3: data backfill. Wrapped in a transaction so a mid-loop
        // failure (e.g., a constraint violation on a malformed row) rolls back
        // cleanly. The surrounding DDL (Steps 1, 1a, 4, 5) auto-commits in
        // MySQL and is not part of this transaction; if a DDL step fails,
        // manual cleanup is still needed, but DDL on nullable columns is much
        // less likely to fail than per-row data writes.
        DB::transaction(function () {
            // Step 2: Backfill events for bookings that have none.
            // Per Chunk 0 audit: 146 of 608 production bookings are in this category.
            $orphans = DB::table('bookings as b')
                ->leftJoin('events as e', function ($join) {
                    $join->on('e.eventable_id', '=', 'b.id')
                         ->where('e.eventable_type', '=', Bookings::class);
                })
                ->whereNull('e.id')
                ->whereNotNull('b.date')  // events.date is NOT NULL; skip date-less bookings.
                ->select('b.id', 'b.name', 'b.event_type_id', 'b.date',
                         'b.start_time', 'b.end_time', 'b.venue_name', 'b.venue_address')
                ->get();

            $now = now();
            foreach ($orphans as $orphan) {
                DB::table('events')->insert([
                    'key'            => Str::uuid()->toString(),
                    'eventable_type' => Bookings::class,
                    'eventable_id'   => $orphan->id,
                    'title'          => $orphan->name,
                    'date'           => $orphan->date,
                    'start_time'     => $orphan->start_time,
                    'end_time'       => $orphan->end_time,
                    'venue_name'     => $orphan->venue_name,
                    'venue_address'  => $orphan->venue_address,
                    'event_type_id'  => $orphan->event_type_id,
                    'created_at'     => $now,
                    'updated_at'     => $now,
                ]);
            }

            // Step 3: Copy booking-level columns onto each booking's primary event.
            // Primary event = first by (date ASC, id ASC) per spec.
            // For OK bookings (462) the primary event already has matching date —
            //   start_time/end_time/venue_name/venue_address get filled in.
            // For backfilled bookings (146) we just inserted the event with these
            //   values already populated, so this UPDATE is a no-op for them
            //   (using IS NULL guards).
            // For the 2 multi-event bookings, the (date, id) ordering picks the
            //   first night, which Chunk 0 verified is correct.
            $bookings = DB::table('bookings')->select('id', 'start_time', 'end_time',
                                                        'venue_name', 'venue_address')->get();

            foreach ($bookings as $booking) {
                $primaryEvent = DB::table('events')
                    ->where('eventable_type', Bookings::class)
                    ->where('eventable_id', $booking->id)
                    ->orderBy('date', 'asc')
                    ->orderBy('id', 'asc')
                    ->first();

                if (!$primaryEvent) {
                    // Should not happen post-backfill, but be defensive.
                    throw new \RuntimeException(
                        "Booking {$booking->id} has no events after backfill; aborting migration."
                    );
                }

                $updates = [];
                if ($primaryEvent->start_time === null && $booking->start_time !== null) {
                    $updates['start_time'] = $booking->start_time;
                }
                if ($primaryEvent->end_time === null && $booking->end_time !== null) {
                    $updates['end_time'] = $booking->end_time;
                }
                if (empty($primaryEvent->venue_name) && !empty($booking->venue_name)) {
                    $updates['venue_name'] = $booking->venue_name;
                }
                if (empty($primaryEvent->venue_address) && !empty($booking->venue_address)) {
                    $updates['venue_address'] = $booking->venue_address;
                }

                if (!empty($updates)) {
                    DB::table('events')->where('id', $primaryEvent->id)->update($updates);
                }
            }
        });

        // Step 4: Drop the columns from bookings.
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['date', 'start_time', 'end_time', 'venue_name', 'venue_address']);
        });

        // Step 5: Drop the legacy events.time column. All values were copied
        // into start_time in Step 1a; backfilled events from Step 2 also have
        // start_time populated directly. The Booking auto-event creation path
        // in BookingsController (Task 5) will be updated to write start_time
        // instead of time.
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('time');
        });
    }

    public function down(): void
    {
        // Reverse: re-add booking columns; copy from each booking's primary
        // event back onto the booking; drop the new event columns.
        Schema::table('bookings', function (Blueprint $table) {
            $table->date('date')->nullable()->after('event_type_id');
            $table->time('start_time')->nullable()->after('date');
            $table->time('end_time')->nullable()->after('start_time');
            $table->string('venue_name')->nullable()->after('end_time');
            $table->string('venue_address')->nullable()->after('venue_name');
        });

        $bookings = DB::table('bookings')->select('id')->get();
        foreach ($bookings as $booking) {
            $primaryEvent = DB::table('events')
                ->where('eventable_type', Bookings::class)
                ->where('eventable_id', $booking->id)
                ->orderBy('date', 'asc')
                ->orderBy('id', 'asc')
                ->first();
            if ($primaryEvent) {
                DB::table('bookings')->where('id', $booking->id)->update([
                    'date'          => $primaryEvent->date,
                    'start_time'    => $primaryEvent->start_time,
                    'end_time'      => $primaryEvent->end_time,
                    'venue_name'    => $primaryEvent->venue_name,
                    'venue_address' => $primaryEvent->venue_address,
                ]);
            }
        }

        // Re-add events.time and copy start_time back into it so any code
        // that still reads $event->time after rollback works.
        Schema::table('events', function (Blueprint $table) {
            $table->time('time')->nullable()->after('date');
        });
        // The WHERE guard preserves NULL start_time as NULL time, matching the
        // up() Step 1a asymmetry (which only copied non-null time values).
        DB::statement('UPDATE events SET time = start_time WHERE start_time IS NOT NULL');

        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['start_time', 'end_time', 'venue_name', 'venue_address', 'price']);
        });
    }
};
