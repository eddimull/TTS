<?php

namespace Tests\Feature\Broadcasting;

use App\Events\BandDataChanged;
use App\Models\Bands;
use App\Models\Bookings;
use App\Models\EventMember;
use App\Models\Events;
use App\Models\EventTypes;
use App\Models\Rehearsal;
use App\Models\Roster;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class BroadcastsBandChangesTest extends TestCase
{
    use RefreshDatabase;

    public function test_booking_create_update_delete_each_broadcast_a_band_signal(): void
    {
        Event::fake([BandDataChanged::class]);
        $band = Bands::factory()->create();

        $booking = Bookings::factory()->create(['band_id' => $band->id]);
        Event::assertDispatched(
            BandDataChanged::class,
            fn (BandDataChanged $e) => $e->bandId === $band->id
                && $e->model === 'bookings'
                && $e->id === $booking->id
                && $e->action === 'created'
                && $e->parent === null,
        );

        $booking->update(['name' => 'Renamed booking']);
        Event::assertDispatched(
            BandDataChanged::class,
            fn (BandDataChanged $e) => $e->id === $booking->id && $e->action === 'updated',
        );

        $booking->delete();
        Event::assertDispatched(
            BandDataChanged::class,
            fn (BandDataChanged $e) => $e->id === $booking->id && $e->action === 'deleted',
        );
    }

    public function test_event_resolves_band_through_its_eventable(): void
    {
        Event::fake([BandDataChanged::class]);
        $band = Bands::factory()->create();
        $booking = Bookings::factory()->create(['band_id' => $band->id]);
        $eventType = EventTypes::factory()->create();

        $event = Events::factory()->create([
            'eventable_id'   => $booking->id,
            'eventable_type' => 'App\\Models\\Bookings',
            'event_type_id'  => $eventType->id,
        ]);

        Event::assertDispatched(
            BandDataChanged::class,
            fn (BandDataChanged $e) => $e->bandId === $band->id
                && $e->model === 'events'
                && $e->id === $event->id
                && $e->action === 'created',
        );
    }

    public function test_event_with_unresolvable_eventable_skips_silently(): void
    {
        Event::fake([BandDataChanged::class]);
        $band = Bands::factory()->create();
        $booking = Bookings::factory()->create(['band_id' => $band->id]);
        $eventType = EventTypes::factory()->create();
        $event = Events::factory()->create([
            'eventable_id'   => $booking->id,
            'eventable_type' => 'App\\Models\\Bookings',
            'event_type_id'  => $eventType->id,
        ]);

        // Orphan the event, then touch it: no band → no signal, and no throw.
        $booking->deleteQuietly();
        $event->refresh();

        Event::fake([BandDataChanged::class]); // reset captured events
        $event->update(['notes' => 'orphaned update']);

        Event::assertNotDispatched(BandDataChanged::class);
    }

    public function test_event_member_signal_carries_its_event_as_parent(): void
    {
        Event::fake([BandDataChanged::class]);
        $band = Bands::factory()->create();
        $booking = Bookings::factory()->create(['band_id' => $band->id]);
        $eventType = EventTypes::factory()->create();
        $event = Events::factory()->create([
            'eventable_id'   => $booking->id,
            'eventable_type' => 'App\\Models\\Bookings',
            'event_type_id'  => $eventType->id,
        ]);

        $member = EventMember::factory()->create([
            'band_id'  => $band->id,
            'event_id' => $event->id,
        ]);

        Event::assertDispatched(
            BandDataChanged::class,
            fn (BandDataChanged $e) => $e->model === 'event_member'
                && $e->id === $member->id
                && $e->parent === ['model' => 'events', 'id' => $event->id],
        );
    }

    public function test_rehearsal_and_roster_broadcast_with_their_band_id(): void
    {
        Event::fake([BandDataChanged::class]);
        $band = Bands::factory()->create();

        $rehearsal = Rehearsal::factory()->create(['band_id' => $band->id]);
        $roster = Roster::factory()->create(['band_id' => $band->id]);

        Event::assertDispatched(
            BandDataChanged::class,
            fn (BandDataChanged $e) => $e->model === 'rehearsal' && $e->id === $rehearsal->id && $e->bandId === $band->id,
        );
        Event::assertDispatched(
            BandDataChanged::class,
            fn (BandDataChanged $e) => $e->model === 'roster' && $e->id === $roster->id && $e->bandId === $band->id,
        );
    }
}
