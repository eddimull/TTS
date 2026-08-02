<?php

namespace Tests\Feature\Api\Mobile;

use App\Models\BandRole;
use App\Models\Bands;
use App\Models\BandSubs;
use App\Models\Events;
use App\Models\EventTypes;
use App\Models\Rehearsal;
use App\Models\RehearsalSchedule;
use App\Models\RehearsalSub;
use App\Models\SubstituteCallList;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RehearsalSubsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // RehearsalSubService::invite() assigns the 'sub' role to registered
        // invitees; Spatie throws if the role does not exist.
        Role::firstOrCreate(['name' => 'sub', 'guard_name' => 'web']);
    }

    /**
     * Owner + band + schedule + one materialized upcoming rehearsal.
     * Mirrors RehearsalsTest::createUserWithBandAndRehearsal().
     */
    private function createOwnerWithRehearsal(int $daysFromNow = 7): array
    {
        $user = User::factory()->create();
        $band = Bands::factory()->create();
        $band->owners()->create(['user_id' => $user->id]);

        $schedule = RehearsalSchedule::factory()->weekly()->create(['band_id' => $band->id]);

        $rehearsal = Rehearsal::factory()->create([
            'rehearsal_schedule_id' => $schedule->id,
            'band_id'               => $band->id,
        ]);

        $eventType = EventTypes::factory()->create();

        $event = Events::factory()->create([
            'eventable_id'   => $rehearsal->id,
            'eventable_type' => 'App\\Models\\Rehearsal',
            'event_type_id'  => $eventType->id,
            'date'           => now()->addDays($daysFromNow)->format('Y-m-d'),
            'start_time'     => '19:00:00',
        ]);

        $token = $user->createToken('test-device')->plainTextToken;

        return compact('user', 'band', 'schedule', 'rehearsal', 'event', 'token');
    }

    public function test_rehearsal_has_subs_relation(): void
    {
        ['rehearsal' => $rehearsal, 'band' => $band] = $this->createOwnerWithRehearsal();

        $sub = RehearsalSub::factory()->create([
            'rehearsal_id' => $rehearsal->id,
            'band_id'      => $band->id,
        ]);

        $this->assertTrue($rehearsal->subs->contains('id', $sub->id));
        $this->assertSame($rehearsal->id, $sub->rehearsal->id);
    }

    public function test_soft_deleted_sub_is_excluded_from_relation(): void
    {
        ['rehearsal' => $rehearsal, 'band' => $band] = $this->createOwnerWithRehearsal();

        $sub = RehearsalSub::factory()->create([
            'rehearsal_id' => $rehearsal->id,
            'band_id'      => $band->id,
        ]);
        $sub->delete();

        $this->assertFalse($rehearsal->fresh()->subs->contains('id', $sub->id));
        $this->assertNotNull(RehearsalSub::withTrashed()->find($sub->id));
    }

    private function postSub(array $ctx, array $body)
    {
        return $this->withToken($ctx['token'])
            ->withHeaders(['X-Band-ID' => $ctx['band']->id])
            ->postJson("/api/mobile/rehearsals/{$ctx['rehearsal']->id}/subs", $body);
    }

    public function test_adhoc_invite_creates_sub_and_returns_subs_list(): void
    {
        $ctx = $this->createOwnerWithRehearsal();

        $response = $this->postSub($ctx, [
            'name'  => 'Pat Horn',
            'email' => 'pat@example.com',
            'phone' => '555-0100',
        ]);

        $response->assertCreated()
            ->assertJsonPath('subs.0.name', 'Pat Horn')
            ->assertJsonPath('subs.0.email', 'pat@example.com')
            ->assertJsonPath('subs.0.is_registered', false);

        $this->assertDatabaseHas('rehearsal_subs', [
            'rehearsal_id' => $ctx['rehearsal']->id,
            'band_id'      => $ctx['band']->id,
            'email'        => 'pat@example.com',
            'user_id'      => null,
            'invited_by'   => $ctx['user']->id,
        ]);
    }

    public function test_adhoc_invite_links_registered_user_and_ensures_band_sub(): void
    {
        $ctx = $this->createOwnerWithRehearsal();
        $registered = User::factory()->create(['email' => 'reg@example.com']);

        $this->postSub($ctx, ['name' => 'Reg', 'email' => 'reg@example.com'])
            ->assertCreated()
            ->assertJsonPath('subs.0.is_registered', true);

        $this->assertDatabaseHas('rehearsal_subs', [
            'rehearsal_id' => $ctx['rehearsal']->id,
            'user_id'      => $registered->id,
        ]);
        $this->assertDatabaseHas('band_subs', [
            'user_id' => $registered->id,
            'band_id' => $ctx['band']->id,
        ]);
    }

    public function test_call_list_invite_resolves_person_and_role(): void
    {
        $ctx = $this->createOwnerWithRehearsal();

        // BandObserver seeds a default role set (Trumpet among them) on band
        // creation, so reuse the existing row rather than colliding with the
        // (band_id, name) unique constraint.
        $role = BandRole::firstOrCreate([
            'band_id' => $ctx['band']->id,
            'name'    => 'Trumpet',
        ]);
        $entry = SubstituteCallList::create([
            'band_id'      => $ctx['band']->id,
            'instrument'   => 'Trumpet',
            'band_role_id' => $role->id,
            'custom_name'  => 'Callie List',
            'custom_email' => 'callie@example.com',
            'priority'     => 1,
        ]);

        $this->postSub($ctx, ['call_list_entry_id' => $entry->id])
            ->assertCreated()
            ->assertJsonPath('subs.0.name', 'Callie List')
            ->assertJsonPath('subs.0.band_role_id', $role->id);
    }

    public function test_adhoc_invite_with_foreign_band_role_id_is_rejected(): void
    {
        $ctx = $this->createOwnerWithRehearsal();
        $otherBand = Bands::factory()->create();
        $foreignRole = BandRole::factory()->create(['band_id' => $otherBand->id]);

        $this->postSub($ctx, [
            'name'         => 'Pat Horn',
            'email'        => 'pat@example.com',
            'band_role_id' => $foreignRole->id,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['band_role_id']);

        $this->assertDatabaseMissing('rehearsal_subs', [
            'rehearsal_id' => $ctx['rehearsal']->id,
            'email'        => 'pat@example.com',
        ]);
    }

    public function test_call_list_entry_from_other_band_is_rejected(): void
    {
        $ctx = $this->createOwnerWithRehearsal();
        $otherBand = Bands::factory()->create();
        $entry = SubstituteCallList::create([
            'band_id'      => $otherBand->id,
            'instrument'   => 'Guitar',
            'custom_name'  => 'Wrong Band',
            'custom_email' => 'wrong@example.com',
            'priority'     => 1,
        ]);

        $this->postSub($ctx, ['call_list_entry_id' => $entry->id])->assertNotFound();
    }

    public function test_duplicate_invite_returns_422(): void
    {
        $ctx = $this->createOwnerWithRehearsal();

        $this->postSub($ctx, ['name' => 'Pat', 'email' => 'pat@example.com'])->assertCreated();
        $this->postSub($ctx, ['name' => 'Pat', 'email' => 'pat@example.com'])
            ->assertUnprocessable();
    }

    public function test_reinvite_after_removal_restores_soft_deleted_row(): void
    {
        $ctx = $this->createOwnerWithRehearsal();

        $this->postSub($ctx, ['name' => 'Pat', 'email' => 'pat@example.com'])->assertCreated();
        $sub = RehearsalSub::where('email', 'pat@example.com')->first();
        $sub->delete();

        $this->postSub($ctx, ['name' => 'Pat', 'email' => 'pat@example.com'])->assertCreated();

        $this->assertSame(1, RehearsalSub::withTrashed()->where('email', 'pat@example.com')->count());
        $this->assertNull($sub->fresh()->deleted_at);
    }

    public function test_invite_to_cancelled_rehearsal_is_blocked(): void
    {
        $ctx = $this->createOwnerWithRehearsal();
        $ctx['rehearsal']->update(['is_cancelled' => true]);

        $this->postSub($ctx, ['name' => 'Pat', 'email' => 'pat@example.com'])
            ->assertUnprocessable();
    }

    public function test_invite_to_past_rehearsal_is_blocked(): void
    {
        $ctx = $this->createOwnerWithRehearsal(daysFromNow: -3);

        $this->postSub($ctx, ['name' => 'Pat', 'email' => 'pat@example.com'])
            ->assertUnprocessable();
    }

    public function test_non_writer_cannot_invite(): void
    {
        $ctx = $this->createOwnerWithRehearsal();

        $outsideSub = User::factory()->create();
        BandSubs::create(['user_id' => $outsideSub->id, 'band_id' => $ctx['band']->id]);
        $subToken = $outsideSub->createToken('sub-device')->plainTextToken;

        $this->withToken($subToken)
            ->withHeaders(['X-Band-ID' => $ctx['band']->id])
            ->postJson("/api/mobile/rehearsals/{$ctx['rehearsal']->id}/subs", [
                'name' => 'X', 'email' => 'x@example.com',
            ])
            ->assertForbidden();
    }

    public function test_detail_includes_subs_array(): void
    {
        $ctx = $this->createOwnerWithRehearsal();
        RehearsalSub::factory()->create([
            'rehearsal_id' => $ctx['rehearsal']->id,
            'band_id'      => $ctx['band']->id,
            'name'         => 'Detail Sub',
        ]);

        $this->withToken($ctx['token'])
            ->withHeaders(['X-Band-ID' => $ctx['band']->id])
            ->getJson("/api/mobile/rehearsals/{$ctx['rehearsal']->id}")
            ->assertOk()
            ->assertJsonPath('rehearsal.subs.0.name', 'Detail Sub')
            ->assertJsonStructure(['rehearsal' => ['subs' => ['*' => [
                'id', 'name', 'email', 'phone', 'band_role_id', 'role_name',
                'user_id', 'is_registered',
            ]]]]);
    }

    public function test_remove_sub_soft_deletes_and_returns_remaining(): void
    {
        $ctx = $this->createOwnerWithRehearsal();

        $keep = RehearsalSub::factory()->create([
            'rehearsal_id' => $ctx['rehearsal']->id,
            'band_id'      => $ctx['band']->id,
            'name'         => 'Keeper',
        ]);
        $remove = RehearsalSub::factory()->create([
            'rehearsal_id' => $ctx['rehearsal']->id,
            'band_id'      => $ctx['band']->id,
            'name'         => 'Removed',
        ]);

        $this->withToken($ctx['token'])
            ->withHeaders(['X-Band-ID' => $ctx['band']->id])
            ->deleteJson("/api/mobile/rehearsals/{$ctx['rehearsal']->id}/subs/{$remove->id}")
            ->assertOk()
            ->assertJsonCount(1, 'subs')
            ->assertJsonPath('subs.0.name', 'Keeper');

        $this->assertSoftDeleted('rehearsal_subs', ['id' => $remove->id]);
        $this->assertNull($keep->fresh()->deleted_at);
    }

    public function test_remove_sub_from_wrong_rehearsal_404s(): void
    {
        $ctx = $this->createOwnerWithRehearsal();
        $otherCtx = $this->createOwnerWithRehearsal();

        $foreignSub = RehearsalSub::factory()->create([
            'rehearsal_id' => $otherCtx['rehearsal']->id,
            'band_id'      => $otherCtx['band']->id,
        ]);

        $this->withToken($ctx['token'])
            ->withHeaders(['X-Band-ID' => $ctx['band']->id])
            ->deleteJson("/api/mobile/rehearsals/{$ctx['rehearsal']->id}/subs/{$foreignSub->id}")
            ->assertNotFound();
    }
}
