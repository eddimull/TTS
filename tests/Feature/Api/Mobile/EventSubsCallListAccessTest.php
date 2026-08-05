<?php

namespace Tests\Feature\Api\Mobile;

use App\Models\BandRole;
use App\Models\Bands;
use App\Models\Bookings;
use App\Models\Events;
use App\Models\EventTypes;
use App\Models\SubstituteCallList;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Access control for the mobile substitute call list endpoint
 * (`GET /api/mobile/events/{event}/subs`).
 *
 * This feeds the sub-assignment picker in the mobile app
 * (EventDetailScreen::_showSubPicker, only reachable via a `canWrite`-gated
 * onAssignSub callback) — it is a band-management surface, not something an
 * assigned sub is ever shown. The endpoint previously gated on
 * `canRead('events', $band->id)`, which User::canRead() grants to ANY sub of
 * the band, letting a sub read every other substitute's name and email
 * address band-wide. It now gates on `canWrite('events', $band->id)`, which
 * has no sub carve-out (matching the sibling assignSub() action on the same
 * controller).
 */
class EventSubsCallListAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'sub', 'guard_name' => 'web']);
    }

    /**
     * A band with one booking + event, a sub assigned to that event, and a
     * call-list entry (with a role) for that event's role.
     */
    private function createBandWithSubAndCallList(): array
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

        $role = BandRole::factory()->create(['band_id' => $band->id]);

        SubstituteCallList::create([
            'band_id'      => $band->id,
            'instrument'   => $role->name,
            'band_role_id' => $role->id,
            'custom_name'  => 'Call List Sub',
            'custom_email' => 'callsub@example.com',
            'priority'     => 1,
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

        return compact('owner', 'band', 'booking', 'event', 'role', 'sub', 'subToken', 'ownerToken');
    }

    public function test_assigned_sub_is_blocked_from_the_call_list(): void
    {
        [
            'event'    => $event,
            'role'     => $role,
            'subToken' => $subToken,
        ] = $this->createBandWithSubAndCallList();

        $this->withToken($subToken)
            ->getJson("/api/mobile/events/{$event->key}/subs?band_role_id={$role->id}")
            ->assertStatus(403);
    }

    public function test_stranger_gets_403(): void
    {
        [
            'event' => $event,
            'role'  => $role,
        ] = $this->createBandWithSubAndCallList();

        $stranger      = User::factory()->create();
        $strangerToken = $stranger->createToken('test-device')->plainTextToken;

        $this->withToken($strangerToken)
            ->getJson("/api/mobile/events/{$event->key}/subs?band_role_id={$role->id}")
            ->assertStatus(403);
    }

    public function test_member_of_another_band_gets_403(): void
    {
        [
            'event' => $event,
            'role'  => $role,
        ] = $this->createBandWithSubAndCallList();

        $otherUser = User::factory()->create();
        $otherBand = Bands::factory()->create();
        $otherBand->owners()->create(['user_id' => $otherUser->id]);
        $otherToken = $otherUser->createToken('test-device')->plainTextToken;

        $this->withToken($otherToken)
            ->getJson("/api/mobile/events/{$event->key}/subs?band_role_id={$role->id}")
            ->assertStatus(403);
    }

    public function test_band_owner_gets_the_call_list(): void
    {
        [
            'event'      => $event,
            'role'       => $role,
            'ownerToken' => $ownerToken,
        ] = $this->createBandWithSubAndCallList();

        $this->withToken($ownerToken)
            ->getJson("/api/mobile/events/{$event->key}/subs?band_role_id={$role->id}")
            ->assertOk()
            ->assertJsonStructure([
                'subs' => [
                    '*' => ['id', 'name', 'email', 'band_role_id', 'role_name', 'roster_member_id', 'is_custom', 'priority'],
                ],
            ])
            ->assertJsonPath('subs.0.name', 'Call List Sub');
    }

    /**
     * Regression: a plain member with an explicit write:events grant (the
     * flow that reaches the sub-assignment picker) must keep working.
     */
    public function test_member_with_write_events_gets_the_call_list(): void
    {
        [
            'band'  => $band,
            'event' => $event,
            'role'  => $role,
        ] = $this->createBandWithSubAndCallList();

        $member = User::factory()->create();
        $band->members()->create(['user_id' => $member->id]);
        setPermissionsTeamId($band->id);
        $member->givePermissionTo('write:events');
        setPermissionsTeamId(0);
        $memberToken = $member->createToken('test-device')->plainTextToken;

        $this->withToken($memberToken)
            ->getJson("/api/mobile/events/{$event->key}/subs?band_role_id={$role->id}")
            ->assertOk()
            ->assertJsonPath('subs.0.name', 'Call List Sub');
    }

    /**
     * A plain member with only read:events (no write) must still be blocked —
     * this is what actually distinguishes the fix from the old canRead() gate.
     */
    public function test_member_with_only_read_events_gets_403(): void
    {
        [
            'band'  => $band,
            'event' => $event,
            'role'  => $role,
        ] = $this->createBandWithSubAndCallList();

        $member = User::factory()->create();
        $band->members()->create(['user_id' => $member->id]);
        setPermissionsTeamId($band->id);
        $member->givePermissionTo('read:events');
        setPermissionsTeamId(0);
        $memberToken = $member->createToken('test-device')->plainTextToken;

        $this->withToken($memberToken)
            ->getJson("/api/mobile/events/{$event->key}/subs?band_role_id={$role->id}")
            ->assertStatus(403);
    }
}
