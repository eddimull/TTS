<?php

namespace Tests\Feature\Push;

use App\Jobs\SendUserPush;
use App\Models\Bands;
use App\Models\Bookings;
use App\Models\DeviceToken;
use App\Models\EventMember;
use App\Models\Events;
use App\Models\PushNotificationLog;
use App\Models\User;
use App\Services\Push\LeaveByPushService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class LeaveByPushServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function makeRosteredEvent(string $date, string $startTime, string $firstItemTime): array
    {
        $band = Bands::factory()->create();
        $booking = Bookings::factory()->create(['band_id' => $band->id]);
        $event = Events::factory()->create([
            'eventable_id'   => $booking->id,
            'eventable_type' => Bookings::class,
            'date'           => $date,
            'start_time'     => $startTime,
            'title'          => 'Gig',
            'venue_address'  => '100 Main St',
            'venue_timezone' => 'America/Chicago',
            'additional_data'=> ['times' => [['title' => 'Load In', 'time' => $firstItemTime]]],
        ]);
        $user = User::factory()->create();
        DeviceToken::factory()->create(['user_id' => $user->id, 'platform' => 'ios']);
        EventMember::create([
            'event_id' => $event->id, 'band_id' => $band->id, 'user_id' => $user->id,
            'attendance_status' => 'confirmed',
        ]);
        return [$event, $user];
    }

    public function test_dispatches_8h_reminder_in_its_window(): void
    {
        Queue::fake();
        [$event, $user] = $this->makeRosteredEvent('2026-06-14', '19:00', '2026-06-14 14:00:00');
        Carbon::setTestNow(Carbon::parse('2026-06-14 11:00:00', 'UTC')); // 14:00 Chicago - 8h = 06:00 CT = 11:00 UTC

        $this->app->make(LeaveByPushService::class)->run(Carbon::now());

        Queue::assertPushed(SendUserPush::class, function ($job) use ($event, $user) {
            return $job->data['type'] === 'event_reminder_8h'
                && $job->dedupeKey === "event:{$event->id}:event_reminder_8h"
                && $job->userId === $user->id
                && $job->data['firstItemTitle'] === 'Load In';
        });
    }

    public function test_dispatches_departure_trigger_in_its_window(): void
    {
        Queue::fake();
        // First item 14:00 Chicago (CDT, UTC-5) - 90min = 12:30 CT = 17:30 UTC.
        [$event, $user] = $this->makeRosteredEvent('2026-06-14', '19:00', '2026-06-14 14:00:00');
        Carbon::setTestNow(Carbon::parse('2026-06-14 17:30:00', 'UTC'));

        $this->app->make(LeaveByPushService::class)->run(Carbon::now());

        Queue::assertPushed(SendUserPush::class, function ($job) use ($event, $user) {
            return $job->data['type'] === 'event_departure'
                && $job->dedupeKey === "event:{$event->id}:event_departure"
                && $job->userId === $user->id;
        });
        // The 8h window (06:00 CT) is long past, so it must NOT also fire.
        Queue::assertNotPushed(SendUserPush::class, fn ($j) => $j->data['type'] === 'event_reminder_8h');
    }

    public function test_does_not_dispatch_when_already_logged(): void
    {
        Queue::fake();
        [$event, $user] = $this->makeRosteredEvent('2026-06-14', '19:00', '2026-06-14 14:00:00');
        PushNotificationLog::create([
            'event_id' => $event->id, 'user_id' => $user->id, 'type' => 'event_reminder_8h',
            'dedupe_key' => "event:{$event->id}:event_reminder_8h",
        ]);
        Carbon::setTestNow(Carbon::parse('2026-06-14 11:00:00', 'UTC'));

        $this->app->make(LeaveByPushService::class)->run(Carbon::now());

        Queue::assertNotPushed(SendUserPush::class, fn ($j) => $j->data['type'] === 'event_reminder_8h');
    }

    public function test_excludes_absent_members(): void
    {
        Queue::fake();
        [$event] = $this->makeRosteredEvent('2026-06-14', '19:00', '2026-06-14 14:00:00');
        EventMember::where('event_id', $event->id)->update(['attendance_status' => 'absent']);
        Carbon::setTestNow(Carbon::parse('2026-06-14 11:00:00', 'UTC'));

        $this->app->make(LeaveByPushService::class)->run(Carbon::now());

        Queue::assertNotPushed(SendUserPush::class);
    }

    public function test_nothing_dispatched_outside_windows(): void
    {
        Queue::fake();
        $this->makeRosteredEvent('2026-06-14', '19:00', '2026-06-14 14:00:00');
        Carbon::setTestNow(Carbon::parse('2026-06-14 02:00:00', 'UTC'));

        $this->app->make(LeaveByPushService::class)->run(Carbon::now());

        Queue::assertNotPushed(SendUserPush::class);
    }
}
