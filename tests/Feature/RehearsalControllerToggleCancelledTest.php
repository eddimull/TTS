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

    public function test_toggle_dispatches_exactly_once_per_request_never_when_value_would_be_unchanged(): void
    {
        Queue::fake();
        ['band' => $band, 'owner' => $owner, 'schedule' => $schedule, 'rehearsal' => $rehearsal] =
            $this->setUpBandWithRehearsal(startCancelled: false);

        $this->actingAs($owner)->post(
            route('rehearsals.toggle-cancelled', [$band, $schedule, $rehearsal])
        );

        // Exactly one dispatch for the one state change that occurred; the
        // controller's guard (mirroring the mobile setCancelled endpoint)
        // must not fire a second, redundant dispatch for a no-op change.
        Queue::assertPushed(ProcessRehearsalCancelled::class, 1);
    }
}
