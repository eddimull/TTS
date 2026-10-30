<?php

namespace Tests\Feature\Broadcasting;

use App\Events\BandDataChanged;
use App\Models\AttireChip;
use App\Models\BandEvents;
use App\Models\Bands;
use App\Models\BookingContacts;
use App\Models\Bookings;
use App\Models\Contacts;
use App\Models\EventAttachment;
use App\Models\EventMember;
use App\Models\Events;
use App\Models\EventSetlist;
use App\Models\EventTypes;
use App\Models\LiveSetlistSession;
use App\Models\Rehearsal;
use App\Models\Roster;
use App\Models\RosterMember;
use App\Models\RosterSlot;
use App\Models\SetlistSong;
use App\Models\Song;
use App\Models\User;
use App\Services\BandMemberRemovalService;
use App\Services\BookingContactService;
use App\Services\GoogleCalendarService;
use App\Services\GoogleDriveOAuthService;
use App\Services\RosterReconcileService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Mockery;
use Tests\TestCase;

/**
 * Realtime signals for everything rendered on the mobile event page.
 * Wire names here are mapped by the Flutter app — do not rename.
 */
class EventRealtimeSignalsTest extends TestCase
{
    use RefreshDatabase;

    private function bookingEvent(?Bands $band = null, array $eventAttrs = []): array
    {
        $band ??= Bands::factory()->create();
        $booking = Bookings::factory()->create(['band_id' => $band->id]);
        $event = Events::factory()->create(array_merge([
            'eventable_id'   => $booking->id,
            'eventable_type' => Bookings::class,
            'event_type_id'  => EventTypes::factory()->create()->id,
            'date'           => now()->addDays(7)->format('Y-m-d'),
        ], $eventAttrs));

        return [$band, $booking, $event];
    }

    /** @return \Illuminate\Support\Collection<int, BandDataChanged> */
    private function signals(string $model)
    {
        return collect(Event::dispatched(BandDataChanged::class))
            ->map(fn (array $args) => $args[0])
            ->filter(fn (BandDataChanged $e) => $e->model === $model)
            ->values();
    }

    // ---------------------------------------------------------------- event_setlist

    public function test_event_setlist_lifecycle_signals_with_event_parent(): void
    {
        [$band, , $event] = $this->bookingEvent();
        Event::fake([BandDataChanged::class]);

        $setlist = EventSetlist::create(['event_id' => $event->id, 'band_id' => $band->id, 'status' => 'draft']);
        $setlist->update(['status' => 'ready']);
        $setlist->delete();

        $signals = $this->signals('event_setlist');
        $this->assertSame(['created', 'updated', 'deleted'], $signals->pluck('action')->all());
        $signals->each(function (BandDataChanged $e) use ($band, $event, $setlist) {
            $this->assertSame($band->id, $e->bandId);
            $this->assertSame($setlist->id, $e->id);
            $this->assertSame(['model' => 'events', 'id' => $event->id], $e->parent);
        });
    }

    public function test_mobile_setlist_reorder_emits_exactly_one_event_setlist_signal(): void
    {
        $user = User::factory()->create();
        [$band, , $event] = $this->bookingEvent();
        $band->owners()->create(['user_id' => $user->id]);

        $songs = Song::factory()->forBand($band)->active()->count(4)->create(['lead_singer_id' => null]);
        $setlist = EventSetlist::create(['event_id' => $event->id, 'band_id' => $band->id, 'status' => 'draft']);
        foreach ($songs as $i => $song) {
            SetlistSong::create(['setlist_id' => $setlist->id, 'type' => 'song', 'song_id' => $song->id, 'position' => $i + 1]);
        }

        Event::fake([BandDataChanged::class]);

        $token = $user->createToken('test-device')->plainTextToken;
        $this->withHeaders([
            'Authorization' => "Bearer {$token}",
            'X-Band-ID'     => $band->id,
            'Accept'        => 'application/json',
        ])->putJson("/api/mobile/events/{$event->key}/setlist", [
            'songs' => $songs->reverse()->values()->map(fn ($s) => ['type' => 'song', 'song_id' => $s->id])->all(),
        ])->assertOk();

        $signals = $this->signals('event_setlist');
        $this->assertCount(1, $signals);
        $this->assertSame($setlist->id, $signals[0]->id);
        $this->assertSame(['model' => 'events', 'id' => $event->id], $signals[0]->parent);
        $this->assertCount(1, Event::dispatched(BandDataChanged::class), 'no per-song signals');
    }

    public function test_mobile_setlist_first_save_with_status_still_emits_one_signal(): void
    {
        $user = User::factory()->create();
        [$band, , $event] = $this->bookingEvent();
        $band->owners()->create(['user_id' => $user->id]);
        $song = Song::factory()->forBand($band)->active()->create(['lead_singer_id' => null]);

        Event::fake([BandDataChanged::class]);

        $token = $user->createToken('test-device')->plainTextToken;
        $this->withHeaders([
            'Authorization' => "Bearer {$token}",
            'X-Band-ID'     => $band->id,
            'Accept'        => 'application/json',
        ])->putJson("/api/mobile/events/{$event->key}/setlist", [
            'songs'  => [['type' => 'song', 'song_id' => $song->id], ['type' => 'break']],
            'status' => 'ready',
        ])->assertOk();

        $this->assertCount(1, $this->signals('event_setlist'));
    }

    // ---------------------------------------------------------------- event_attachment

    public function test_event_attachment_signals_with_event_parent(): void
    {
        [$band, , $event] = $this->bookingEvent();
        Event::fake([BandDataChanged::class]);

        $attachment = EventAttachment::factory()->create(['event_id' => $event->id, 'disk' => 'local']);
        $attachment->delete();

        $signals = $this->signals('event_attachment');
        $this->assertSame(['created', 'deleted'], $signals->pluck('action')->all());
        $this->assertSame($band->id, $signals[0]->bandId);
        $this->assertSame(['model' => 'events', 'id' => $event->id], $signals[0]->parent);
    }

    // ---------------------------------------------------------------- live_setlist_session

    public function test_live_session_signals_only_on_lifecycle_changes(): void
    {
        $user = User::factory()->create();
        [$band, , $event] = $this->bookingEvent();
        Event::fake([BandDataChanged::class]);

        $session = LiveSetlistSession::create([
            'event_id'         => $event->id,
            'band_id'          => $band->id,
            'started_by'       => $user->id,
            'status'           => 'active',
            'current_position' => 1,
            'started_at'       => now(),
        ]);

        // Song advances must not hit the band channel.
        $session->update(['current_position' => 2]);
        $session->update(['current_position' => 3, 'after_break' => true]);

        $session->update(['status' => 'completed', 'ended_at' => now()]);

        $signals = $this->signals('live_setlist_session');
        $this->assertSame(['created', 'updated'], $signals->pluck('action')->all());
        $this->assertSame($band->id, $signals[0]->bandId);
        $this->assertSame(['model' => 'events', 'id' => $event->id], $signals[0]->parent);
    }

    // ---------------------------------------------------------------- roster_member / roster_slot

    public function test_roster_member_and_slot_signal_with_roster_parent(): void
    {
        $band = Bands::factory()->create();
        $roster = Roster::factory()->create(['band_id' => $band->id]);
        Event::fake([BandDataChanged::class]);

        $slot = RosterSlot::create(['roster_id' => $roster->id, 'name' => 'Guitar', 'quantity' => 1]);
        $member = RosterMember::factory()->create(['roster_id' => $roster->id, 'slot_id' => $slot->id]);
        $member->update(['notes' => 'changed']);
        $slot->delete();

        $slotSignals = $this->signals('roster_slot');
        $this->assertSame(['created', 'deleted'], $slotSignals->pluck('action')->all());
        $this->assertSame(['model' => 'roster', 'id' => $roster->id], $slotSignals[0]->parent);
        $this->assertSame($band->id, $slotSignals[0]->bandId);

        $memberSignals = $this->signals('roster_member');
        $this->assertSame(['created', 'updated'], $memberSignals->pluck('action')->all());
        $this->assertSame(['model' => 'roster', 'id' => $roster->id], $memberSignals[0]->parent);
        $this->assertSame($band->id, $memberSignals[0]->bandId);
    }

    // ---------------------------------------------------------------- attire_chip

    public function test_attire_chip_signals(): void
    {
        $band = Bands::factory()->create();
        Event::fake([BandDataChanged::class]);

        $chip = (new AttireChip())->forceFill(['band_id' => $band->id, 'label' => 'All black', 'position' => 0]);
        $chip->save();
        $chip->delete();

        $signals = $this->signals('attire_chip');
        $this->assertSame(['created', 'deleted'], $signals->pluck('action')->all());
        $this->assertSame($band->id, $signals[0]->bandId);
    }

    public function test_attire_chip_first_save_seeding_emits_one_signal(): void
    {
        $user = User::factory()->create();
        $band = Bands::factory()->create();
        $band->owners()->create(['user_id' => $user->id]);
        Event::fake([BandDataChanged::class]);

        $token = $user->createToken('test-device')->plainTextToken;
        $this->withHeaders([
            'Authorization' => "Bearer {$token}",
            'X-Band-ID'     => $band->id,
            'Accept'        => 'application/json',
        ])->postJson("/api/mobile/bands/{$band->id}/attire-chips", ['label' => 'Sequins'])->assertOk();

        $this->assertGreaterThan(1, AttireChip::where('band_id', $band->id)->count(), 'defaults were seeded');
        $this->assertCount(1, $this->signals('attire_chip'));
    }

    // ---------------------------------------------------------------- booking contacts → bookings

    public function test_attaching_a_contact_signals_the_booking(): void
    {
        [$band, $booking] = $this->bookingEvent();
        Event::fake([BandDataChanged::class]);

        app(BookingContactService::class)->attach($band, $booking, [
            'name' => 'Jane Client', 'email' => 'jane@example.com', 'role' => 'Bride',
        ]);

        $signals = $this->signals('bookings');
        $this->assertCount(1, $signals);
        $this->assertSame($booking->id, $signals[0]->id);
        $this->assertSame($band->id, $signals[0]->bandId);
    }

    public function test_editing_or_removing_a_booking_contact_row_signals_the_booking(): void
    {
        [$band, $booking] = $this->bookingEvent();
        $contact = Contacts::factory()->create(['band_id' => $band->id]);
        $bookingContact = BookingContacts::create(['booking_id' => $booking->id, 'contact_id' => $contact->id, 'role' => 'Bride']);
        Event::fake([BandDataChanged::class]);

        $bookingContact->update(['role' => 'Planner']);
        $bookingContact->delete();

        $signals = $this->signals('bookings');
        $this->assertCount(2, $signals);
        $signals->each(fn (BandDataChanged $e) => $this->assertSame($booking->id, $e->id));
    }

    public function test_editing_a_contact_signals_each_attached_booking(): void
    {
        [$band, $bookingA] = $this->bookingEvent();
        [, $bookingB] = $this->bookingEvent($band);
        $contact = Contacts::factory()->create(['band_id' => $band->id]);
        $bookingA->contacts()->attach($contact->id);
        $bookingB->contacts()->attach($contact->id);
        Event::fake([BandDataChanged::class]);

        $contact->update(['phone' => '555-0100']);

        $this->assertEqualsCanonicalizing(
            [$bookingA->id, $bookingB->id],
            $this->signals('bookings')->pluck('id')->all(),
        );

        // Non-displayed fields stay silent.
        Event::fake([BandDataChanged::class]);
        $contact->update(['password' => bcrypt('secret-pass')]);
        $this->assertCount(0, $this->signals('bookings'));
    }

    // ---------------------------------------------------------------- bulk event_member deletes

    public function test_roster_reconcile_bulk_delete_signals_once_per_event(): void
    {
        $band = Bands::factory()->create();
        $roster = Roster::factory()->create(['band_id' => $band->id]);
        $member = RosterMember::factory()->create(['roster_id' => $roster->id, 'user_id' => null]);
        [, , $eventA] = $this->bookingEvent($band, ['roster_id' => $roster->id]);
        [, , $eventB] = $this->bookingEvent($band, ['roster_id' => $roster->id]);
        foreach ([$eventA, $eventB] as $event) {
            EventMember::factory()->create([
                'event_id' => $event->id, 'band_id' => $band->id, 'roster_member_id' => $member->id,
            ]);
        }
        Event::fake([BandDataChanged::class]);

        $removed = app(RosterReconcileService::class)->removeMemberFromFutureEvents($member);

        $this->assertGreaterThanOrEqual(2, $removed);
        $signals = $this->signals('event_member');
        $this->assertEqualsCanonicalizing(
            [$eventA->id, $eventB->id],
            $signals->pluck('parent.id')->all(),
        );
        $signals->each(function (BandDataChanged $e) use ($band) {
            $this->assertSame('deleted', $e->action);
            $this->assertSame($band->id, $e->bandId);
        });
    }

    public function test_band_member_removal_bulk_delete_signals_once_per_event(): void
    {
        $user = User::factory()->create();
        $band = Bands::factory()->create();
        $band->members()->create(['user_id' => $user->id]);
        [, , $eventA] = $this->bookingEvent($band);
        [, , $eventB] = $this->bookingEvent($band);
        foreach ([$eventA, $eventB] as $event) {
            EventMember::factory()->create([
                'event_id' => $event->id, 'band_id' => $band->id, 'user_id' => $user->id, 'roster_member_id' => null,
            ]);
        }
        // External Google services resolve credentials at construction time.
        $this->app->instance(GoogleCalendarService::class, Mockery::mock(GoogleCalendarService::class));
        $this->app->instance(GoogleDriveOAuthService::class, Mockery::mock(GoogleDriveOAuthService::class));
        Event::fake([BandDataChanged::class]);

        app(BandMemberRemovalService::class)->removeFromBand($band, $user);

        $this->assertEqualsCanonicalizing(
            [$eventA->id, $eventB->id],
            $this->signals('event_member')->pluck('parent.id')->all(),
        );
    }

    // ---------------------------------------------------------------- band_events + trashed eventables

    public function test_band_events_signal(): void
    {
        $band = Bands::factory()->create();
        Event::fake([BandDataChanged::class]);

        $bandEvent = BandEvents::factory()->create(['band_id' => $band->id]);

        $signals = $this->signals('band_events');
        $this->assertCount(1, $signals);
        $this->assertSame($bandEvent->id, $signals[0]->id);
    }

    public function test_event_on_soft_deleted_rehearsal_still_resolves_band(): void
    {
        $band = Bands::factory()->create();
        $rehearsal = Rehearsal::factory()->create(['band_id' => $band->id]);
        $event = Events::factory()->create([
            'eventable_id'   => $rehearsal->id,
            'eventable_type' => Rehearsal::class,
            'event_type_id'  => EventTypes::factory()->create()->id,
        ]);
        $rehearsal->delete();
        $event = Events::find($event->id);

        Event::fake([BandDataChanged::class]);
        $event->update(['notes' => 'after parent trashed']);

        $signals = $this->signals('events');
        $this->assertCount(1, $signals);
        $this->assertSame($band->id, $signals[0]->bandId);
    }
}
