<?php

namespace Tests\Feature\Broadcasting;

use App\Events\BandDataChanged;
use App\Models\Bands;
use App\Models\Bookings;
use App\Models\EventMember;
use App\Models\Events;
use App\Models\EventTypes;
use App\Models\Payout;
use App\Models\PayoutAdjustment;
use App\Models\Rehearsal;
use App\Models\Roster;
use App\Models\User;
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

    public function test_payment_on_a_booking_broadcasts_with_booking_parent(): void
    {
        Event::fake([BandDataChanged::class]);
        $band = Bands::factory()->create();
        $booking = Bookings::factory()->create(['band_id' => $band->id]);

        $payment = $booking->payments()->create([
            'name' => 'Deposit',
            'amount' => 5000,
            'date' => now(),
            'band_id' => $band->id,
        ]);

        Event::assertDispatched(
            BandDataChanged::class,
            fn (BandDataChanged $e) => $e->model === 'payments'
                && $e->id === $payment->id
                && $e->bandId === $band->id
                && $e->action === 'created'
                && $e->parent === ['model' => 'bookings', 'id' => $booking->id],
        );
    }

    public function test_payout_cache_recalculation_does_not_broadcast(): void
    {
        $band = Bands::factory()->create();
        $booking = Bookings::factory()->create(['band_id' => $band->id]);
        $payout = Payout::create([
            'payable_type' => Bookings::class,
            'payable_id' => $booking->id,
            'band_id' => $band->id,
            'base_amount' => 10000,
            'adjusted_amount' => 10000,
        ]);

        // The Payout page GET rewrites these derived caches on every render.
        // If this dispatched, signal -> partial reload -> re-save would loop
        // the page forever (the production incident this test pins).
        Event::fake([BandDataChanged::class]);
        $payout->update(['calculation_result' => ['total' => 100.0]]);

        Event::assertNotDispatched(BandDataChanged::class);
    }

    public function test_song_and_chart_assets_broadcast(): void
    {
        Event::fake([BandDataChanged::class]);
        $band = Bands::factory()->create();

        $song = \App\Models\Song::create([
            'band_id' => $band->id,
            'title' => 'Realtime Anthem',
        ]);
        Event::assertDispatched(
            BandDataChanged::class,
            fn (BandDataChanged $e) => $e->model === 'song' && $e->id === $song->id && $e->bandId === $band->id,
        );

        $chart = \App\Models\Charts::create([
            'band_id' => $band->id,
            'title' => 'Realtime Chart',
        ]);
        Event::assertDispatched(
            BandDataChanged::class,
            fn (BandDataChanged $e) => $e->model === 'charts' && $e->id === $chart->id && $e->bandId === $band->id,
        );

        $uploadTypeId = \DB::table('upload_types')->insertGetId(['name' => 'pdf']);
        $upload = \App\Models\ChartUploads::create([
            'chart_id' => $chart->id,
            'upload_type_id' => $uploadTypeId,
            'displayName' => 'Horns.pdf',
            'url' => 'charts/horns.pdf',
            'fileType' => 'pdf',
            'notes' => '',
        ]);
        Event::assertDispatched(
            BandDataChanged::class,
            fn (BandDataChanged $e) => $e->model === 'chart_uploads'
                && $e->bandId === $band->id
                && $e->parent === ['model' => 'charts', 'id' => $chart->id],
        );
    }

    public function test_media_file_create_and_delete_broadcast(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        Event::fake([BandDataChanged::class]);
        $band = Bands::factory()->create();
        $user = User::factory()->create();

        $media = \App\Models\MediaFile::create([
            'band_id' => $band->id,
            'user_id' => $user->id,
            'filename' => 'poster.jpg',
            'stored_filename' => 'media/poster.jpg',
            'mime_type' => 'image/jpeg',
            'file_size' => 1024,
            'disk' => 'local',
            'media_type' => 'image',
            'folder_path' => '/',
        ]);

        Event::assertDispatched(
            BandDataChanged::class,
            fn (BandDataChanged $e) => $e->model === 'media_file'
                && $e->id === $media->id
                && $e->bandId === $band->id
                && $e->action === 'created',
        );

        $media->delete();

        Event::assertDispatched(
            BandDataChanged::class,
            fn (BandDataChanged $e) => $e->model === 'media_file' && $e->action === 'deleted',
        );
    }

    public function test_payout_page_renders_are_silent_once_config_converged(): void
    {
        $user = User::factory()->create();
        $band = Bands::factory()->create();
        $band->owners()->create(['user_id' => $user->id]);
        $booking = Bookings::factory()->create(['band_id' => $band->id, 'price' => 1000]);
        \App\Models\BandPayoutConfig::create(['band_id' => $band->id, 'name' => 'Active', 'is_active' => true]);
        Payout::create([
            'payable_type' => Bookings::class,
            'payable_id' => $booking->id,
            'band_id' => $band->id,
            'base_amount' => 100000,
            'adjusted_amount' => 100000,
        ]);

        // First render adopts the active config — one settling signal is fine.
        $this->actingAs($user)->get(route('Booking Payout', [$band, $booking]))->assertOk();

        // Once converged, every further render must be broadcast-silent.
        // This pins the exact cycle of the production reload-loop incident:
        // signal -> client partial reload -> this GET re-saves its cache.
        Event::fake([BandDataChanged::class]);
        $this->actingAs($user)->get(route('Booking Payout', [$band, $booking]))->assertOk();

        Event::assertNotDispatched(BandDataChanged::class);
    }

    public function test_switching_payout_configuration_broadcasts(): void
    {
        $band = Bands::factory()->create();
        $booking = Bookings::factory()->create(['band_id' => $band->id]);
        $config = \App\Models\BandPayoutConfig::create([
            'band_id' => $band->id,
            'name' => 'Realtime config',
            'is_active' => true,
        ]);
        $payout = Payout::create([
            'payable_type' => Bookings::class,
            'payable_id' => $booking->id,
            'band_id' => $band->id,
            'base_amount' => 10000,
            'adjusted_amount' => 10000,
        ]);

        // The config-switch POST writes payout_config_id + calculation_result
        // together; the recomputed cache is ignored but the switch itself
        // must reach other clients (regression: the first ignore list
        // silenced this exact mutation).
        Event::fake([BandDataChanged::class]);
        $payout->update([
            'payout_config_id' => $config->id,
            'calculation_result' => ['total' => 1.0],
        ]);

        Event::assertDispatched(
            BandDataChanged::class,
            fn (BandDataChanged $e) => $e->model === 'payout' && $e->action === 'updated',
        );
    }

    public function test_meaningful_payout_change_still_broadcasts(): void
    {
        $band = Bands::factory()->create();
        $booking = Bookings::factory()->create(['band_id' => $band->id]);
        $payout = Payout::create([
            'payable_type' => Bookings::class,
            'payable_id' => $booking->id,
            'band_id' => $band->id,
            'base_amount' => 10000,
            'adjusted_amount' => 10000,
        ]);

        Event::fake([BandDataChanged::class]);
        $payout->update(['adjusted_amount' => 12000]);

        Event::assertDispatched(
            BandDataChanged::class,
            fn (BandDataChanged $e) => $e->model === 'payout' && $e->action === 'updated',
        );
    }

    public function test_timestamp_only_touch_does_not_broadcast(): void
    {
        $band = Bands::factory()->create();
        $booking = Bookings::factory()->create(['band_id' => $band->id]);

        Event::fake([BandDataChanged::class]);
        $booking->touch();

        Event::assertNotDispatched(BandDataChanged::class);
    }

    public function test_payout_adjustment_resolves_band_through_its_payout(): void
    {
        Event::fake([BandDataChanged::class]);
        $band = Bands::factory()->create();
        $booking = Bookings::factory()->create(['band_id' => $band->id]);
        $user = User::factory()->create();

        $payout = Payout::create([
            'payable_type' => Bookings::class,
            'payable_id' => $booking->id,
            'band_id' => $band->id,
            'base_amount' => 10000,
            'adjusted_amount' => 10000,
        ]);

        $adjustment = PayoutAdjustment::create([
            'payout_id' => $payout->id,
            'created_by' => $user->id,
            'amount' => 500,
            'description' => 'Bonus',
        ]);

        Event::assertDispatched(
            BandDataChanged::class,
            fn (BandDataChanged $e) => $e->model === 'payout_adjustment'
                && $e->id === $adjustment->id
                && $e->bandId === $band->id
                && $e->parent === ['model' => 'bookings', 'id' => $booking->id],
        );
    }
}
