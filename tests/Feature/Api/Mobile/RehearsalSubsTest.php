<?php

namespace Tests\Feature\Api\Mobile;

use App\Models\Bands;
use App\Models\Events;
use App\Models\EventTypes;
use App\Models\Rehearsal;
use App\Models\RehearsalSchedule;
use App\Models\RehearsalSub;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RehearsalSubsTest extends TestCase
{
    use RefreshDatabase;

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
}
