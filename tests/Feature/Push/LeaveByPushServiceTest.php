<?php

namespace Tests\Feature\Push;

use App\Jobs\SendEventPush;
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

        Queue::assertPushed(SendEventPush::class, function ($job) use ($event, $user) {
            return $job->type === 'event_reminder_8h'
                && $job->eventId === $event->id
                && $job->userId === $user->id
                && $job->payload['type'] === 'event_reminder_8h'
                && $job->payload['firstItemTitle'] === 'Load In';
        });
    }

    public function test_does_not_dispatch_when_already_logged(): void
    {
        Queue::fake();
        [$event, $user] = $this->makeRosteredEvent('2026-06-14', '19:00', '2026-06-14 14:00:00');
        PushNotificationLog::create(['event_id' => $event->id, 'user_id' => $user->id, 'type' => 'event_reminder_8h']);
        Carbon::setTestNow(Carbon::parse('2026-06-14 11:00:00', 'UTC'));

        $this->app->make(LeaveByPushService::class)->run(Carbon::now());

        Queue::assertNotPushed(SendEventPush::class, fn ($j) => $j->type === 'event_reminder_8h');
    }

    public function test_excludes_absent_members(): void
    {
        Queue::fake();
        [$event] = $this->makeRosteredEvent('2026-06-14', '19:00', '2026-06-14 14:00:00');
        EventMember::where('event_id', $event->id)->update(['attendance_status' => 'absent']);
        Carbon::setTestNow(Carbon::parse('2026-06-14 11:00:00', 'UTC'));

        $this->app->make(LeaveByPushService::class)->run(Carbon::now());

        Queue::assertNotPushed(SendEventPush::class);
    }

    public function test_nothing_dispatched_outside_windows(): void
    {
        Queue::fake();
        $this->makeRosteredEvent('2026-06-14', '19:00', '2026-06-14 14:00:00');
        Carbon::setTestNow(Carbon::parse('2026-06-14 02:00:00', 'UTC'));

        $this->app->make(LeaveByPushService::class)->run(Carbon::now());

        Queue::assertNotPushed(SendEventPush::class);
    }
}
