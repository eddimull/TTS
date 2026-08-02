<?php

namespace Tests\Feature\Api\Mobile;

use App\Jobs\ProcessRehearsalSubAdded;
use App\Jobs\SendUserPush;
use App\Mail\RehearsalSubAdded;
use App\Models\Bands;
use App\Models\DeviceToken;
use App\Models\Events;
use App\Models\EventTypes;
use App\Models\Rehearsal;
use App\Models\RehearsalSchedule;
use App\Models\RehearsalSub;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class RehearsalSubNotificationsTest extends TestCase
{
    use RefreshDatabase;

    private function createRehearsalWithSub(?User $subUser = null): array
    {
        $owner = User::factory()->create();
        $band  = Bands::factory()->create();
        $band->owners()->create(['user_id' => $owner->id]);

        $schedule  = RehearsalSchedule::factory()->weekly()->create(['band_id' => $band->id]);
        $rehearsal = Rehearsal::factory()->create([
            'rehearsal_schedule_id' => $schedule->id,
            'band_id'               => $band->id,
        ]);
        Events::factory()->create([
            'eventable_id'   => $rehearsal->id,
            'eventable_type' => 'App\\Models\\Rehearsal',
            'event_type_id'  => EventTypes::factory()->create()->id,
            'date'           => now()->addDays(7)->format('Y-m-d'),
            'start_time'     => '19:00:00',
        ]);

        $sub = RehearsalSub::factory()->create([
            'rehearsal_id' => $rehearsal->id,
            'band_id'      => $band->id,
            'user_id'      => $subUser?->id,
            'email'        => $subUser?->email ?? 'adhoc@example.com',
            'invited_by'   => $owner->id,
        ]);

        return compact('owner', 'band', 'rehearsal', 'sub');
    }

    public function test_store_endpoint_dispatches_added_job(): void
    {
        Queue::fake();

        $owner = User::factory()->create();
        $band  = Bands::factory()->create();
        $band->owners()->create(['user_id' => $owner->id]);
        $schedule  = RehearsalSchedule::factory()->weekly()->create(['band_id' => $band->id]);
        $rehearsal = Rehearsal::factory()->create([
            'rehearsal_schedule_id' => $schedule->id,
            'band_id'               => $band->id,
        ]);
        Events::factory()->create([
            'eventable_id'   => $rehearsal->id,
            'eventable_type' => 'App\\Models\\Rehearsal',
            'event_type_id'  => EventTypes::factory()->create()->id,
            'date'           => now()->addDays(7)->format('Y-m-d'),
        ]);
        $token = $owner->createToken('t')->plainTextToken;

        $this->withToken($token)
            ->withHeaders(['X-Band-ID' => $band->id])
            ->postJson("/api/mobile/rehearsals/{$rehearsal->id}/subs", [
                'name' => 'Pat', 'email' => 'pat@example.com',
            ])
            ->assertCreated();

        Queue::assertPushed(ProcessRehearsalSubAdded::class);
    }

    public function test_added_job_emails_adhoc_invitee_without_push(): void
    {
        Mail::fake();
        Queue::fake();

        ['sub' => $sub, 'owner' => $owner] = $this->createRehearsalWithSub();

        (new ProcessRehearsalSubAdded($sub, $owner->id, 'test-dedupe'))->handle();

        Mail::assertSent(RehearsalSubAdded::class,
            fn ($mail) => $mail->hasTo('adhoc@example.com'));
        Queue::assertNotPushed(SendUserPush::class);
    }

    public function test_added_job_emails_and_pushes_registered_sub_with_device(): void
    {
        Mail::fake();
        Queue::fake();

        $subUser = User::factory()->create();
        DeviceToken::factory()->create(['user_id' => $subUser->id]);

        ['sub' => $sub, 'owner' => $owner] = $this->createRehearsalWithSub($subUser);

        (new ProcessRehearsalSubAdded($sub, $owner->id, 'test-dedupe'))->handle();

        Mail::assertSent(RehearsalSubAdded::class,
            fn ($mail) => $mail->hasTo($subUser->email));
        Queue::assertPushed(SendUserPush::class);
    }
}
