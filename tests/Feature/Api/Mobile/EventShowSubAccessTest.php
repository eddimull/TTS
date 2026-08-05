<?php

namespace Tests\Feature\Api\Mobile;

use App\Models\Bands;
use App\Models\Bookings;
use App\Models\Events;
use App\Models\EventTypes;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Per-gig scoping for the mobile event detail endpoint
 * (`GET /api/mobile/events/{event}`).
 *
 * The endpoint gated only on `canRead('events', $band->id)`, which
 * User::canRead() grants to ANY sub of the band with no per-gig check. Since
 * Events::resolveRouteBinding() also accepts numeric ids, a sub's token could
 * enumerate every gig in their band and read contacts, attachments and notes
 * for gigs they were never called for.
 *
 * Unassigned subs get a 404 (hide existence) rather than a 403, matching the
 * LodgingsController::show() precedent.
 */
class EventShowSubAccessTest extends TestCase
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
     * NOTE: the band_subs pivot relation lives on User (`User::bandSub`), not
     * on Bands. isSubOfBand() reads the loaded relation, so the cached (empty)
     * copy must be dropped after the attach.
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
        $sub->unsetRelation('bandSub');

        $ownerToken = $owner->createToken('test-device')->plainTextToken;
        $subToken   = $sub->createToken('test-device')->plainTextToken;

        return compact('owner', 'band', 'booking', 'event', 'sub', 'subToken', 'ownerToken');
    }

    /**
     * A second gig in the same band that the sub is NOT assigned to.
     */
    private function createUnassignedEvent(Bands $band): Events
    {
        $otherBooking = Bookings::factory()->create(['band_id' => $band->id]);

        return Events::factory()->create([
            'eventable_id'   => $otherBooking->id,
            'eventable_type' => 'App\\Models\\Bookings',
            'event_type_id'  => EventTypes::factory()->create()->id,
            'date'           => now()->addDays(9)->format('Y-m-d'),
        ]);
    }

    public function test_assigned_sub_gets_full_event_detail(): void
    {
        ['event' => $event, 'subToken' => $subToken] = $this->createBandWithSubAndEvent();

        $response = $this->withToken($subToken)
            ->getJson("/api/mobile/events/{$event->key}")
            ->assertOk()
            ->assertJsonStructure([
                'event' => [
                    'id', 'key', 'title', 'date', 'time', 'notes',
                    'event_type', 'venue_name', 'venue_address',
                    'can_write', 'members',
                ],
            ]);

        $this->assertEquals($event->id, $response->json('event.id'));
    }

    public function test_unassigned_sub_of_the_same_band_gets_404_by_key(): void
    {
        ['band' => $band, 'subToken' => $subToken] = $this->createBandWithSubAndEvent();

        $otherEvent = $this->createUnassignedEvent($band);

        $this->withToken($subToken)
            ->getJson("/api/mobile/events/{$otherEvent->key}")
            ->assertStatus(404);
    }

    /**
     * resolveRouteBinding() accepts numeric ids too, which is the enumerable
     * surface — a sub could walk /api/mobile/events/1,2,3...
     */
    public function test_unassigned_sub_of_the_same_band_gets_404_by_numeric_id(): void
    {
        ['band' => $band, 'subToken' => $subToken] = $this->createBandWithSubAndEvent();

        $otherEvent = $this->createUnassignedEvent($band);

        $this->withToken($subToken)
            ->getJson("/api/mobile/events/{$otherEvent->id}")
            ->assertStatus(404);
    }

    public function test_full_member_still_sees_event_they_are_not_assigned_to(): void
    {
        ['band' => $band] = $this->createBandWithSubAndEvent();

        $unassignedEvent = $this->createUnassignedEvent($band);

        // Members (unlike owners) don't bypass the permission check — they need
        // an explicit team-scoped read:events grant to pass canRead().
        $member = User::factory()->create();
        $band->members()->create(['user_id' => $member->id]);
        setPermissionsTeamId($band->id);
        $member->givePermissionTo('read:events');
        setPermissionsTeamId(0);
        $memberToken = $member->createToken('test-device')->plainTextToken;

        $this->withToken($memberToken)
            ->getJson("/api/mobile/events/{$unassignedEvent->key}")
            ->assertOk()
            ->assertJsonPath('event.id', $unassignedEvent->id);
    }

    public function test_band_owner_still_sees_event(): void
    {
        ['band' => $band, 'ownerToken' => $ownerToken] = $this->createBandWithSubAndEvent();

        $unassignedEvent = $this->createUnassignedEvent($band);

        $this->withToken($ownerToken)
            ->getJson("/api/mobile/events/{$unassignedEvent->key}")
            ->assertOk();
    }

    public function test_member_of_another_band_still_gets_403(): void
    {
        ['event' => $event] = $this->createBandWithSubAndEvent();

        $otherUser = User::factory()->create();
        $otherBand = Bands::factory()->create();
        $otherBand->owners()->create(['user_id' => $otherUser->id]);
        $otherToken = $otherUser->createToken('test-device')->plainTextToken;

        $this->withToken($otherToken)
            ->getJson("/api/mobile/events/{$event->key}")
            ->assertStatus(403);
    }

    /**
     * A sub of band A must not reach band B's gigs at all — canRead() fails
     * first, so this stays a 403 rather than the per-gig 404.
     */
    public function test_sub_of_one_band_cannot_read_another_bands_event(): void
    {
        ['subToken' => $subToken] = $this->createBandWithSubAndEvent();

        $otherOwner = User::factory()->create();
        $otherBand  = Bands::factory()->create();
        $otherBand->owners()->create(['user_id' => $otherOwner->id]);
        $foreignEvent = $this->createUnassignedEvent($otherBand);

        $this->withToken($subToken)
            ->getJson("/api/mobile/events/{$foreignEvent->key}")
            ->assertStatus(403);
    }
}
