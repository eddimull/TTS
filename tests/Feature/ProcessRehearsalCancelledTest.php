<?php

namespace Tests\Feature;

use App\Jobs\ProcessRehearsalCancelled;
use App\Jobs\ProcessEventUpdated;
use App\Jobs\SendUserPush;
use App\Models\Bands;
use App\Models\DeviceToken;
use App\Models\Events;
use App\Models\EventTypes;
use App\Models\Rehearsal;
use App\Models\RehearsalSchedule;
use App\Models\User;
use App\Notifications\RehearsalCancelled;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ProcessRehearsalCancelledTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{rehearsal: Rehearsal, actor: User, member: User, memberWithDevice: User} */
    private function setUpBandWithRehearsal(): array
    {
        $actor  = User::factory()->create();
        $member = User::factory()->create();
        $memberWithDevice = User::factory()->create();

        $band = Bands::factory()->create();
        $band->owners()->create(['user_id' => $actor->id]);
        $band->members()->create(['user_id' => $member->id]);
        $band->members()->create(['user_id' => $memberWithDevice->id]);

        DeviceToken::create(['user_id' => $memberWithDevice->id, 'token' => 'tok-x', 'platform' => 'android']);

        $schedule = RehearsalSchedule::factory()->weekly()->create([
            'band_id' => $band->id,
            'name'    => 'Tuesday Practice',
        ]);
        $rehearsal = Rehearsal::factory()->create([
            'rehearsal_schedule_id' => $schedule->id,
            'band_id'               => $band->id,
        ]);
        Events::factory()->create([
            'eventable_id'   => $rehearsal->id,
            'eventable_type' => 'App\\Models\\Rehearsal',
            'event_type_id'  => EventTypes::factory()->create()->id,
            'date'           => now()->addDays(5)->format('Y-m-d'),
            'start_time'     => '19:00:00',
        ]);

        return compact('rehearsal', 'actor', 'member', 'memberWithDevice');
    }

    public function test_notifies_everyone_except_actor(): void
    {
        Notification::fake();
        Queue::fake();
        ['rehearsal' => $rehearsal, 'actor' => $actor, 'member' => $member, 'memberWithDevice' => $withDevice] =
            $this->setUpBandWithRehearsal();

        (new ProcessRehearsalCancelled($rehearsal, $actor->id, true, 'key-1'))->handle();

        Notification::assertSentTo($member, RehearsalCancelled::class);
        Notification::assertSentTo($withDevice, RehearsalCancelled::class);
        Notification::assertNotSentTo($actor, RehearsalCancelled::class);
    }

    public function test_push_only_to_members_with_device_tokens(): void
    {
        Notification::fake();
        Queue::fake();
        ['rehearsal' => $rehearsal, 'actor' => $actor, 'member' => $member, 'memberWithDevice' => $withDevice] =
            $this->setUpBandWithRehearsal();

        (new ProcessRehearsalCancelled($rehearsal, $actor->id, true, 'key-2'))->handle();

        Queue::assertPushed(SendUserPush::class, function (SendUserPush $job) use ($withDevice, $rehearsal) {
            return $job->userId === $withDevice->id
                && $job->alert === true
                && $job->dedupeKey === 'key-2'
                && $job->data['type'] === 'rehearsal_cancelled'
                && $job->data['rehearsalId'] === (string) $rehearsal->id
                && $job->data['title'] !== ''
                && $job->data['body'] !== '';
        });
        Queue::assertNotPushed(SendUserPush::class, fn (SendUserPush $job) => $job->userId === $member->id);
        Queue::assertNotPushed(SendUserPush::class, fn (SendUserPush $job) => $job->userId === $actor->id);
    }

    public function test_restore_sends_restored_type(): void
    {
        Notification::fake();
        Queue::fake();
        ['rehearsal' => $rehearsal, 'actor' => $actor] = $this->setUpBandWithRehearsal();

        (new ProcessRehearsalCancelled($rehearsal, $actor->id, false, 'key-3'))->handle();

        Queue::assertPushed(SendUserPush::class, fn (SendUserPush $job) => $job->data['type'] === 'rehearsal_restored');
    }

    public function test_user_who_is_owner_and_member_is_notified_once(): void
    {
        Notification::fake();
        Queue::fake();
        ['rehearsal' => $rehearsal, 'actor' => $actor, 'member' => $member] =
            $this->setUpBandWithRehearsal();

        $band = $rehearsal->rehearsalSchedule->band;
        $overlapUser = User::factory()->create();
        $band->owners()->create(['user_id' => $overlapUser->id]);
        $band->members()->create(['user_id' => $overlapUser->id]);
        DeviceToken::create(['user_id' => $overlapUser->id, 'token' => 'tok-overlap', 'platform' => 'android']);

        (new ProcessRehearsalCancelled($rehearsal, $actor->id, true, 'key-4'))->handle();

        Notification::assertSentToTimes($overlapUser, RehearsalCancelled::class, 1);
        $this->assertSame(
            1,
            Queue::pushed(SendUserPush::class, fn ($job) => $job->userId === $overlapUser->id)->count(),
            'Overlap user must receive exactly one push',
        );

        // Sanity: unrelated member is unaffected by the overlap.
        Notification::assertSentTo($member, RehearsalCancelled::class);
    }

    public function test_dispatches_calendar_resync_for_backing_event(): void
    {
        Notification::fake();
        Queue::fake();
        ['rehearsal' => $rehearsal, 'actor' => $actor] = $this->setUpBandWithRehearsal();

        (new ProcessRehearsalCancelled($rehearsal, $actor->id, true, 'key-cal-1'))->handle();

        Queue::assertPushed(ProcessEventUpdated::class);
    }

    public function test_no_calendar_resync_when_rehearsal_has_no_backing_event(): void
    {
        Notification::fake();
        Queue::fake();

        $actor = User::factory()->create();
        $band  = Bands::factory()->create();
        $band->owners()->create(['user_id' => $actor->id]);
        $schedule = RehearsalSchedule::factory()->weekly()->create([
            'band_id' => $band->id,
            'name'    => 'No Event Practice',
        ]);
        $rehearsal = Rehearsal::factory()->create([
            'rehearsal_schedule_id' => $schedule->id,
            'band_id'               => $band->id,
        ]);

        (new ProcessRehearsalCancelled($rehearsal, $actor->id, true, 'key-cal-2'))->handle();

        Queue::assertNotPushed(ProcessEventUpdated::class);
    }
}
