<?php

namespace Tests\Feature\Api\Mobile;

use App\Models\Bands;
use App\Models\BandSubs;
use App\Models\Events;
use App\Models\EventTypes;
use App\Models\Rehearsal;
use App\Models\RehearsalSchedule;
use App\Models\RehearsalSub;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RehearsalSubVisibilityTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A band (with an owner), one upcoming materialized rehearsal, and a
     * separate sub user linked via band_subs. Returns everything needed to
     * add rehearsal_subs rows and mint sub tokens.
     */
    private function createBandWithSubUser(): array
    {
        $owner = User::factory()->create();
        $band  = Bands::factory()->create();
        $band->owners()->create(['user_id' => $owner->id]);

        $schedule = RehearsalSchedule::factory()->weekly()->create(['band_id' => $band->id]);

        $rehearsal = Rehearsal::factory()->create([
            'rehearsal_schedule_id' => $schedule->id,
            'band_id'               => $band->id,
        ]);

        $eventType = EventTypes::factory()->create();
        Events::factory()->create([
            'eventable_id'   => $rehearsal->id,
            'eventable_type' => 'App\\Models\\Rehearsal',
            'event_type_id'  => $eventType->id,
            'date'           => now()->addDays(7)->format('Y-m-d'),
            'start_time'     => '19:00:00',
        ]);

        $subUser = User::factory()->create();
        BandSubs::create(['user_id' => $subUser->id, 'band_id' => $band->id]);

        return compact('owner', 'band', 'schedule', 'rehearsal', 'subUser');
    }

    public function test_sub_without_invite_cannot_read_rehearsals(): void
    {
        ['band' => $band, 'subUser' => $subUser] = $this->createBandWithSubUser();

        $this->assertFalse($subUser->canRead('rehearsals', $band->id));
        $this->assertFalse($subUser->hasRehearsalSubAssignmentForBand($band->id));
    }

    public function test_sub_with_live_invite_can_read_rehearsals_but_not_as_member(): void
    {
        ['band' => $band, 'rehearsal' => $rehearsal, 'subUser' => $subUser] =
            $this->createBandWithSubUser();

        RehearsalSub::factory()->create([
            'rehearsal_id' => $rehearsal->id,
            'band_id'      => $band->id,
            'user_id'      => $subUser->id,
            'email'        => $subUser->email,
        ]);

        $this->assertTrue($subUser->canRead('rehearsals', $band->id));
        $this->assertTrue($subUser->hasRehearsalSubAssignmentForBand($band->id));
        $this->assertFalse($subUser->canReadRehearsalsAsMember($band->id));
    }

    public function test_soft_deleted_invite_does_not_grant_read(): void
    {
        ['band' => $band, 'rehearsal' => $rehearsal, 'subUser' => $subUser] =
            $this->createBandWithSubUser();

        $sub = RehearsalSub::factory()->create([
            'rehearsal_id' => $rehearsal->id,
            'band_id'      => $band->id,
            'user_id'      => $subUser->id,
            'email'        => $subUser->email,
        ]);
        $sub->delete();

        $this->assertFalse($subUser->canRead('rehearsals', $band->id));
    }

    public function test_owner_reads_rehearsals_as_member(): void
    {
        ['band' => $band, 'owner' => $owner] = $this->createBandWithSubUser();

        $this->assertTrue($owner->canReadRehearsalsAsMember($band->id));
    }

    public function test_sub_schedule_list_contains_only_invited_rehearsals(): void
    {
        ['band' => $band, 'schedule' => $schedule, 'rehearsal' => $invited, 'subUser' => $subUser] =
            $this->createBandWithSubUser();

        // A second upcoming rehearsal the sub is NOT invited to.
        $other = Rehearsal::factory()->create([
            'rehearsal_schedule_id' => $schedule->id,
            'band_id'               => $band->id,
        ]);
        Events::factory()->create([
            'eventable_id'   => $other->id,
            'eventable_type' => 'App\\Models\\Rehearsal',
            'event_type_id'  => EventTypes::factory()->create()->id,
            'date'           => now()->addDays(14)->format('Y-m-d'),
        ]);

        RehearsalSub::factory()->create([
            'rehearsal_id' => $invited->id,
            'band_id'      => $band->id,
            'user_id'      => $subUser->id,
            'email'        => $subUser->email,
        ]);

        // Token minted AFTER the invite so it carries read:rehearsals.
        $token = $subUser->createToken('sub-device')->plainTextToken;

        $response = $this->withToken($token)
            ->withHeaders(['X-Band-ID' => $band->id])
            ->getJson("/api/mobile/bands/{$band->id}/rehearsal-schedules?include_virtual=1");

        $response->assertOk();

        $upcoming = collect($response->json('schedules'))
            ->flatMap(fn ($s) => $s['upcoming_rehearsals']);

        $this->assertTrue($upcoming->pluck('id')->contains($invited->id));
        $this->assertFalse($upcoming->pluck('id')->contains($other->id));
        // No virtual expansion for sub-scoped users even when requested.
        $this->assertFalse($upcoming->contains(fn ($r) => $r['id'] === null));
    }

    public function test_sub_can_view_invited_rehearsal_detail_but_not_others(): void
    {
        ['band' => $band, 'schedule' => $schedule, 'rehearsal' => $invited, 'subUser' => $subUser] =
            $this->createBandWithSubUser();

        $other = Rehearsal::factory()->create([
            'rehearsal_schedule_id' => $schedule->id,
            'band_id'               => $band->id,
        ]);
        Events::factory()->create([
            'eventable_id'   => $other->id,
            'eventable_type' => 'App\\Models\\Rehearsal',
            'event_type_id'  => EventTypes::factory()->create()->id,
            'date'           => now()->addDays(14)->format('Y-m-d'),
        ]);

        RehearsalSub::factory()->create([
            'rehearsal_id' => $invited->id,
            'band_id'      => $band->id,
            'user_id'      => $subUser->id,
            'email'        => $subUser->email,
        ]);
        $token = $subUser->createToken('sub-device')->plainTextToken;

        $this->withToken($token)
            ->withHeaders(['X-Band-ID' => $band->id])
            ->getJson("/api/mobile/rehearsals/{$invited->id}")
            ->assertOk();

        $this->withToken($token)
            ->withHeaders(['X-Band-ID' => $band->id])
            ->getJson("/api/mobile/rehearsals/{$other->id}")
            ->assertForbidden();
    }

    public function test_sub_cannot_materialize_virtual_rehearsals(): void
    {
        ['band' => $band, 'schedule' => $schedule, 'rehearsal' => $invited, 'subUser' => $subUser] =
            $this->createBandWithSubUser();

        RehearsalSub::factory()->create([
            'rehearsal_id' => $invited->id,
            'band_id'      => $band->id,
            'user_id'      => $subUser->id,
            'email'        => $subUser->email,
        ]);
        $token = $subUser->createToken('sub-device')->plainTextToken;

        $futureDate = now()->addDays(21)->format('Y-m-d');

        $this->withToken($token)
            ->withHeaders(['X-Band-ID' => $band->id])
            ->getJson("/api/mobile/rehearsals/by-key/virtual-rehearsal-{$schedule->id}-{$futureDate}")
            ->assertForbidden();
    }
}
