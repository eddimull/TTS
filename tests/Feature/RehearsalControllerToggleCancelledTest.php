<?php

namespace Tests\Feature;

use App\Jobs\ProcessRehearsalCancelled;
use App\Models\Bands;
use App\Models\Rehearsal;
use App\Models\RehearsalSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class RehearsalControllerToggleCancelledTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{band: Bands, owner: User, schedule: RehearsalSchedule, rehearsal: Rehearsal} */
    private function setUpBandWithRehearsal(bool $startCancelled = false): array
    {
        $band  = Bands::factory()->create();
        $owner = User::factory()->create();
        $band->owners()->create(['user_id' => $owner->id]);

        $schedule = RehearsalSchedule::factory()->weekly()->create([
            'band_id' => $band->id,
            'name'    => 'Tuesday Practice',
        ]);

        $rehearsal = Rehearsal::factory()->create([
            'rehearsal_schedule_id' => $schedule->id,
            'band_id'               => $band->id,
            'is_cancelled'          => $startCancelled,
        ]);

        return compact('band', 'owner', 'schedule', 'rehearsal');
    }

    public function test_toggling_an_active_rehearsal_dispatches_process_rehearsal_cancelled(): void
    {
        Queue::fake();
        ['band' => $band, 'owner' => $owner, 'schedule' => $schedule, 'rehearsal' => $rehearsal] =
            $this->setUpBandWithRehearsal(startCancelled: false);

        $response = $this->actingAs($owner)->post(
            route('rehearsals.toggle-cancelled', [$band, $schedule, $rehearsal])
        );

        $response->assertRedirect();
        $this->assertTrue($rehearsal->fresh()->is_cancelled);

        Queue::assertPushed(ProcessRehearsalCancelled::class, function (ProcessRehearsalCancelled $job) use ($rehearsal, $owner) {
            return $job->rehearsal->id === $rehearsal->id
                && $job->actorId === $owner->id
                && $job->isCancelled === true;
        });
    }

    public function test_toggling_a_cancelled_rehearsal_dispatches_restore(): void
    {
        Queue::fake();
        ['band' => $band, 'owner' => $owner, 'schedule' => $schedule, 'rehearsal' => $rehearsal] =
            $this->setUpBandWithRehearsal(startCancelled: true);

        $response = $this->actingAs($owner)->post(
            route('rehearsals.toggle-cancelled', [$band, $schedule, $rehearsal])
        );

        $response->assertRedirect();
        $this->assertFalse($rehearsal->fresh()->is_cancelled);

        Queue::assertPushed(ProcessRehearsalCancelled::class, function (ProcessRehearsalCancelled $job) use ($rehearsal, $owner) {
            return $job->rehearsal->id === $rehearsal->id
                && $job->actorId === $owner->id
                && $job->isCancelled === false;
        });
    }

    public function test_toggle_dispatches_exactly_once_per_request(): void
    {
        Queue::fake();
        ['band' => $band, 'owner' => $owner, 'schedule' => $schedule, 'rehearsal' => $rehearsal] =
            $this->setUpBandWithRehearsal(startCancelled: false);

        $this->actingAs($owner)->post(
            route('rehearsals.toggle-cancelled', [$band, $schedule, $rehearsal])
        );

        // The single POST flips is_cancelled once, so exactly one dispatch
        // is expected — no double-dispatch, no dedup logic to verify here.
        Queue::assertPushed(ProcessRehearsalCancelled::class, 1);
    }

    public function test_toggle_returns_404_when_schedule_does_not_belong_to_band(): void
    {
        Queue::fake();

        // Attacker has write access on band A.
        $bandA      = Bands::factory()->create();
        $attacker   = User::factory()->create();
        $bandA->owners()->create(['user_id' => $attacker->id]);

        // The rehearsal + schedule under attack belong to band B.
        [
            'band'      => $bandB,
            'schedule'  => $scheduleB,
            'rehearsal' => $rehearsalB,
        ] = $this->setUpBandWithRehearsal(startCancelled: false);

        // Attacker crafts a URL using their own band A, but band B's schedule
        // and rehearsal ids.
        $response = $this->actingAs($attacker)->post(
            route('rehearsals.toggle-cancelled', [$bandA, $scheduleB, $rehearsalB])
        );

        $response->assertNotFound();
        $this->assertFalse($rehearsalB->fresh()->is_cancelled);
        Queue::assertNotPushed(ProcessRehearsalCancelled::class);
    }

    public function test_toggle_returns_404_when_rehearsal_does_not_belong_to_schedule(): void
    {
        Queue::fake();

        // Attacker has write access on band A, and band A does have its own
        // schedule — but the rehearsal id in the URL belongs to band B's
        // schedule, so the rehearsal/schedule pairing is incoherent.
        $bandA      = Bands::factory()->create();
        $attacker   = User::factory()->create();
        $bandA->owners()->create(['user_id' => $attacker->id]);
        $scheduleA  = RehearsalSchedule::factory()->weekly()->create([
            'band_id' => $bandA->id,
            'name'    => 'Attacker Schedule',
        ]);

        [
            'band'      => $bandB,
            'rehearsal' => $rehearsalB,
        ] = $this->setUpBandWithRehearsal(startCancelled: false);

        $response = $this->actingAs($attacker)->post(
            route('rehearsals.toggle-cancelled', [$bandA, $scheduleA, $rehearsalB])
        );

        $response->assertNotFound();
        $this->assertFalse($rehearsalB->fresh()->is_cancelled);
        Queue::assertNotPushed(ProcessRehearsalCancelled::class);
    }
}
