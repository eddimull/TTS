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
}
