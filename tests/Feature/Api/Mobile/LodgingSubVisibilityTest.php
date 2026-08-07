<?php

namespace Tests\Feature\Api\Mobile;

use App\Models\Bands;
use App\Models\Bookings;
use App\Models\Events;
use App\Models\EventTypes;
use App\Models\Lodging;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Sub-role visibility for lodgings.
 *
 * A sub is NOT a band member — they pass the `read:lodging` gate only via the
 * User::canRead() carve-out (isSubOfBand + hasCurrentSubAssignmentForBand), and
 * even then they must only ever see stays attached to the specific gigs they
 * are assigned to. These tests are the security net for that scoping; this
 * codebase has a history of cross-band sub leaks, so they assert on exact
 * result sets (assertSame on names) rather than "contains".
 */
class LodgingSubVisibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'sub', 'guard_name' => 'web']);
    }

    /**
     * A band with one booking + event, and a sub assigned to that event.
     *
     * NOTE: the band_subs pivot relation lives on User (`User::bandSub`), not on
     * Bands — there is no `Bands::bandSub()`. Attach from the user side.
     */
    private function createBandWithSubAndEvent(): array
    {
        $owner = User::factory()->create();
        $band  = Bands::factory()->create();
        $band->owners()->create(['user_id' => $owner->id]);

        $booking = Bookings::factory()->create(['band_id' => $band->id]);
        $event   = Events::factory()->create([
            'eventable_id'   => $booking->id,
            'eventable_type' => 'App\\Models\\Bookings',
            'event_type_id'  => EventTypes::factory()->create()->id,
            'date'           => now()->addDays(7)->format('Y-m-d'),
        ]);

        $sub = User::factory()->create();
        $sub->bandSub()->attach($band->id);
        $sub->ensureGlobalSubRole();
        DB::table('event_subs')->insert([
            'event_id'       => $event->id,
            'band_id'        => $band->id,
            'user_id'        => $sub->id,
            'invitation_key' => Str::uuid(),
            'pending'        => false,
            'accepted_at'    => now(),
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);
        // isSubOfBand() reads the loaded `bandSub` relation, so drop the cached
        // (empty) copy populated before the attach above.
        $sub->unsetRelation('bandSub');

        $subToken = $sub->createToken('test-device')->plainTextToken;

        return compact('band', 'booking', 'event', 'sub', 'subToken');
    }

    public function test_sub_sees_only_lodgings_linked_to_their_assigned_event(): void
    {
        ['band' => $band, 'event' => $event, 'subToken' => $subToken] = $this->createBandWithSubAndEvent();

        Lodging::factory()->create([
            'band_id' => $band->id, 'name' => 'Linked Hotel', 'event_id' => $event->id,
        ]);
        Lodging::factory()->create([
            'band_id' => $band->id, 'name' => 'Unlinked Hotel',
        ]);

        $response = $this->withToken($subToken)
            ->withHeaders(['X-Band-ID' => $band->id])
            ->getJson("/api/mobile/bands/{$band->id}/lodgings")
            ->assertOk()
            ->json();

        $this->assertSame(['Linked Hotel'], array_column($response['lodgings'], 'name'));
        $this->assertFalse($response['can_write']);
    }

    public function test_sub_sees_lodging_linked_to_the_booking_of_their_assigned_event(): void
    {
        ['band' => $band, 'booking' => $booking, 'subToken' => $subToken] = $this->createBandWithSubAndEvent();

        Lodging::factory()->create([
            'band_id' => $band->id, 'name' => 'Booking Hotel', 'booking_id' => $booking->id,
        ]);
        Lodging::factory()->create([
            'band_id' => $band->id, 'name' => 'Unlinked Hotel',
        ]);

        $response = $this->withToken($subToken)
            ->withHeaders(['X-Band-ID' => $band->id])
            ->getJson("/api/mobile/bands/{$band->id}/lodgings")
            ->assertOk()
            ->json();

        $this->assertSame(['Booking Hotel'], array_column($response['lodgings'], 'name'));
    }

    public function test_sub_does_not_see_lodging_for_an_event_they_are_not_assigned_to(): void
    {
        ['band' => $band, 'subToken' => $subToken] = $this->createBandWithSubAndEvent();

        // A second gig in the same band that the sub is NOT on.
        $otherBooking = Bookings::factory()->create(['band_id' => $band->id]);
        $otherEvent   = Events::factory()->create([
            'eventable_id'   => $otherBooking->id,
            'eventable_type' => 'App\\Models\\Bookings',
            'event_type_id'  => EventTypes::factory()->create()->id,
            'date'           => now()->addDays(9)->format('Y-m-d'),
        ]);
        Lodging::factory()->create([
            'band_id' => $band->id, 'name' => 'Other Gig Hotel', 'event_id' => $otherEvent->id,
        ]);

        $response = $this->withToken($subToken)
            ->withHeaders(['X-Band-ID' => $band->id])
            ->getJson("/api/mobile/bands/{$band->id}/lodgings")
            ->assertOk()
            ->json();

        $this->assertSame([], array_column($response['lodgings'], 'name'));
    }

    public function test_sub_cannot_open_unlinked_lodging_detail(): void
    {
        ['band' => $band, 'subToken' => $subToken] = $this->createBandWithSubAndEvent();
        $unlinked = Lodging::factory()->create(['band_id' => $band->id]);

        $this->withToken($subToken)
            ->withHeaders(['X-Band-ID' => $band->id])
            ->getJson("/api/mobile/bands/{$band->id}/lodgings/{$unlinked->id}")
            ->assertStatus(404);
    }

    public function test_sub_can_open_linked_lodging_detail(): void
    {
        ['band' => $band, 'event' => $event, 'subToken' => $subToken] = $this->createBandWithSubAndEvent();
        $linked = Lodging::factory()->create([
            'band_id' => $band->id, 'name' => 'Linked Hotel', 'event_id' => $event->id,
        ]);

        $response = $this->withToken($subToken)
            ->withHeaders(['X-Band-ID' => $band->id])
            ->getJson("/api/mobile/bands/{$band->id}/lodgings/{$linked->id}")
            ->assertOk()
            ->json();

        $this->assertSame('Linked Hotel', $response['lodging']['name']);
        $this->assertFalse($response['can_write']);
    }

    public function test_sub_cannot_write_lodgings(): void
    {
        ['band' => $band, 'event' => $event, 'subToken' => $subToken] = $this->createBandWithSubAndEvent();

        $this->withToken($subToken)
            ->withHeaders(['X-Band-ID' => $band->id])
            ->postJson("/api/mobile/bands/{$band->id}/lodgings", [
                'name'     => 'Sub Made This',
                'event_id' => $event->id,
            ])
            ->assertStatus(403);

        $this->assertDatabaseMissing('lodgings', ['name' => 'Sub Made This']);
    }

    /**
     * The event-detail payload gained a `lodgings` key (Task 4). That endpoint
     * used to gate only on canRead('events'), which ANY sub of the band passes
     * with no assignment requirement, so the key had to be scoped at the
     * formatter or a sub could read hotel names, addresses and confirmation
     * counts for gigs they are not on.
     *
     * The endpoint now applies the per-gig gate itself (EventsController::show
     * — see EventShowSubAccessTest) and 404s an unassigned sub before any
     * payload is built, so the whole event — not just its stays — is hidden.
     * Kept as the lodging-specific regression on that behaviour.
     */
    public function test_sub_does_not_see_lodgings_on_detail_of_an_event_they_are_not_assigned_to(): void
    {
        ['band' => $band, 'subToken' => $subToken] = $this->createBandWithSubAndEvent();

        $otherBooking = Bookings::factory()->create(['band_id' => $band->id]);
        $otherEvent   = Events::factory()->create([
            'eventable_id'   => $otherBooking->id,
            'eventable_type' => 'App\\Models\\Bookings',
            'event_type_id'  => EventTypes::factory()->create()->id,
            'date'           => now()->addDays(9)->format('Y-m-d'),
        ]);
        Lodging::factory()->create([
            'band_id' => $band->id, 'name' => 'Other Gig Hotel', 'event_id' => $otherEvent->id,
        ]);

        $this->withToken($subToken)
            ->getJson("/api/mobile/events/{$otherEvent->key}")
            ->assertStatus(404)
            ->assertDontSee('Other Gig Hotel');
    }

    public function test_sub_sees_lodgings_on_detail_of_their_own_event(): void
    {
        ['band' => $band, 'event' => $event, 'subToken' => $subToken] = $this->createBandWithSubAndEvent();

        Lodging::factory()->create([
            'band_id' => $band->id, 'name' => 'My Gig Hotel', 'event_id' => $event->id,
        ]);

        $response = $this->withToken($subToken)
            ->getJson("/api/mobile/events/{$event->key}")
            ->assertOk()
            ->json();

        $this->assertSame(['My Gig Hotel'], array_column($response['event']['lodgings'], 'name'));
    }

    /**
     * Regression for TTS-BAND-16B, HTTP level. A HYBRID viewer — sub on this
     * band's gig who also owns/belongs to another band — skips the pure-sub
     * early return in UserEventsService::getEvents() and takes the band path,
     * which is the only path that reaches generateUpcomingRehearsals() (and
     * therefore the only path that hit the Carbon-flavor TypeError; the
     * pure-sub test above could never catch it). Pins that such a viewer gets
     * a 200 AND still sees their assigned gig's stays.
     */
    public function test_hybrid_sub_with_own_band_sees_lodgings_on_detail_of_their_assigned_event(): void
    {
        ['band' => $band, 'event' => $event, 'sub' => $sub, 'subToken' => $subToken] = $this->createBandWithSubAndEvent();

        $ownBand = Bands::factory()->create();
        $ownBand->owners()->create(['user_id' => $sub->id]);

        Lodging::factory()->create([
            'band_id' => $band->id, 'name' => 'My Gig Hotel', 'event_id' => $event->id,
        ]);

        $response = $this->withToken($subToken)
            ->getJson("/api/mobile/events/{$event->key}")
            ->assertOk()
            ->json();

        $this->assertSame(['My Gig Hotel'], array_column($response['event']['lodgings'], 'name'));
    }

    /**
     * The booking-detail payload also gained a `lodgings` key, but that route
     * requires `read:bookings`, which has no sub carve-out — so a sub never
     * reaches the payload at all. Pinned so a future carve-out for bookings
     * can't silently expose booking-level lodgings to unassigned subs.
     */
    public function test_sub_cannot_reach_booking_detail_payload_at_all(): void
    {
        ['band' => $band, 'booking' => $booking, 'subToken' => $subToken] = $this->createBandWithSubAndEvent();

        Lodging::factory()->create([
            'band_id' => $band->id, 'name' => 'Booking Hotel', 'booking_id' => $booking->id,
        ]);

        $this->withToken($subToken)
            ->withHeaders(['X-Band-ID' => $band->id])
            ->getJson("/api/mobile/bands/{$band->id}/bookings/{$booking->id}")
            ->assertStatus(403);
    }

    public function test_sub_of_one_band_cannot_read_another_bands_lodgings(): void
    {
        ['sub' => $sub, 'subToken' => $subToken] = $this->createBandWithSubAndEvent();

        // A band the sub has no relationship with at all.
        $otherOwner = User::factory()->create();
        $otherBand  = Bands::factory()->create();
        $otherBand->owners()->create(['user_id' => $otherOwner->id]);
        Lodging::factory()->create(['band_id' => $otherBand->id, 'name' => 'Foreign Hotel']);

        $this->withToken($subToken)
            ->withHeaders(['X-Band-ID' => $otherBand->id])
            ->getJson("/api/mobile/bands/{$otherBand->id}/lodgings")
            ->assertStatus(403);
    }

    /**
     * C1 regression: LodgingAttachmentsController::show() (the sanctum serve
     * route) previously gated only on User::canRead('lodging', $bandId),
     * which is band-wide for a sub. A sub assigned to one gig could fetch
     * another gig's attachments by enumerating sequential attachment ids.
     * The serve route must apply the same per-stay subCanSee() gate as
     * LodgingsController::show().
     */
    public function test_sub_can_fetch_attachment_of_their_assigned_lodging_via_serve_route(): void
    {
        \Illuminate\Support\Facades\Storage::fake(config('filesystems.default'));
        ['band' => $band, 'event' => $event, 'subToken' => $subToken] = $this->createBandWithSubAndEvent();

        $linked = Lodging::factory()->create([
            'band_id' => $band->id, 'name' => 'Linked Hotel', 'event_id' => $event->id,
        ]);
        $attachment = $linked->attachments()->create([
            'filename' => 'confirmation.jpg', 'stored_filename' => 'x/confirmation.jpg',
            'mime_type' => 'image/jpeg', 'file_size' => 1, 'disk' => config('filesystems.default'),
        ]);
        \Illuminate\Support\Facades\Storage::disk($attachment->disk)->put($attachment->stored_filename, 'bytes');

        $this->withToken($subToken)
            ->getJson("/api/mobile/lodging-attachments/{$attachment->id}")
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_sub_cannot_fetch_attachment_of_an_unlinked_lodging_via_serve_route(): void
    {
        \Illuminate\Support\Facades\Storage::fake(config('filesystems.default'));
        ['band' => $band, 'subToken' => $subToken] = $this->createBandWithSubAndEvent();

        $unlinked = Lodging::factory()->create(['band_id' => $band->id, 'name' => 'Unlinked Hotel']);
        $attachment = $unlinked->attachments()->create([
            'filename' => 'secret.jpg', 'stored_filename' => 'x/secret.jpg',
            'mime_type' => 'image/jpeg', 'file_size' => 1, 'disk' => config('filesystems.default'),
        ]);
        \Illuminate\Support\Facades\Storage::disk($attachment->disk)->put($attachment->stored_filename, 'bytes');

        $this->withToken($subToken)
            ->getJson("/api/mobile/lodging-attachments/{$attachment->id}")
            ->assertStatus(404);
    }
}
