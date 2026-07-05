<?php

namespace Tests\Feature\Push;

use App\Jobs\SendUserPush;
use App\Models\Bands;
use App\Models\Bookings;
use App\Models\DeviceToken;
use App\Models\EventMember;
use App\Models\Events;
use App\Models\User;
use App\Services\Push\LeaveByPushService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PayloadContractTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_payload_keys_match_mobile_contract(): void
    {
        Queue::fake();
        $band = Bands::factory()->create();
        $booking = Bookings::factory()->create(['band_id' => $band->id]);
        $event = Events::factory()->create([
            'eventable_id' => $booking->id, 'eventable_type' => Bookings::class,
            'date' => '2026-06-14', 'start_time' => '19:00', 'title' => 'Gig',
            'venue_address' => '100 Main St', 'venue_timezone' => 'America/Chicago',
            'additional_data' => ['times' => [['title' => 'Load In', 'time' => '2026-06-14 14:00:00']]],
        ]);
        $user = User::factory()->create();
        DeviceToken::factory()->create(['user_id' => $user->id, 'platform' => 'ios']);
        EventMember::create(['event_id' => $event->id, 'band_id' => $band->id, 'user_id' => $user->id, 'attendance_status' => 'confirmed']);

        Carbon::setTestNow(Carbon::parse('2026-06-14 11:00:00', 'UTC'));
        $this->app->make(LeaveByPushService::class)->run(Carbon::now());

        Queue::assertPushed(SendUserPush::class, function ($job) {
            $allowed = ['type', 'eventKey', 'title', 'body', 'venueAddress', 'firstItemTitle', 'firstItemTime', 'showTime'];
            foreach (array_keys($job->data) as $k) {
                if (!in_array($k, $allowed, true)) {
                    return false;
                }
            }
            return $job->data['type'] === 'event_reminder_8h'
                && $job->data['eventKey'] !== ''
                && array_key_exists('body', $job->data)
                && $job->data['body'] !== ''
                && array_key_exists('firstItemTitle', $job->data)
                && array_key_exists('showTime', $job->data);
        });
    }
}
