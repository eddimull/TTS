<?php

namespace Tests\Feature\Push;

use App\Jobs\SendEventPush;
use App\Models\Bands;
use App\Models\Bookings;
use App\Models\DeviceToken;
use App\Models\Events;
use App\Models\PushNotificationLog;
use App\Models\User;
use App\Services\Push\FcmSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class _FakeFcm extends FcmSender
{
    public array $sent = [];
    public string $result = FcmSender::DELIVERED;
    public function __construct() {}
    public function sendData(string $token, array $data): string
    {
        $this->sent[] = ['token' => $token, 'data' => $data];
        return $this->result;
    }
}

class SendEventPushTest extends TestCase
{
    use RefreshDatabase;

    private function event(): Events
    {
        $band = Bands::factory()->create();
        $booking = Bookings::factory()->create(['band_id' => $band->id]);
        return Events::factory()->create([
            'eventable_id'   => $booking->id,
            'eventable_type' => Bookings::class,
            'title'          => 'Gig',
            'venue_address'  => '100 Main St',
        ]);
    }

    public function test_sends_data_only_payload_to_each_token_and_logs(): void
    {
        $fake = new _FakeFcm();
        $this->app->instance(FcmSender::class, $fake);

        $user = User::factory()->create();
        DeviceToken::factory()->create(['user_id' => $user->id, 'token' => 'a', 'platform' => 'ios']);
        DeviceToken::factory()->create(['user_id' => $user->id, 'token' => 'b', 'platform' => 'android']);
        $event = $this->event();

        (new SendEventPush(
            eventId: $event->id,
            userId: $user->id,
            type: 'event_reminder_8h',
            payload: [
                'type' => 'event_reminder_8h',
                'eventKey' => $event->key,
                'title' => 'Gig',
                'venueAddress' => '100 Main St',
                'firstItemTitle' => 'Load In',
                'firstItemTime' => '2026-06-14T14:00:00-05:00',
                'showTime' => '2026-06-14T19:00:00-05:00',
            ],
        ))->handle($fake);

        $this->assertCount(2, $fake->sent);
        $this->assertSame('event_reminder_8h', $fake->sent[0]['data']['type']);
        $this->assertSame($event->key, $fake->sent[0]['data']['eventKey']);
        $this->assertDatabaseHas('push_notification_log', [
            'event_id' => $event->id, 'user_id' => $user->id, 'type' => 'event_reminder_8h',
        ]);
    }

    public function test_prunes_dead_tokens(): void
    {
        $fake = new _FakeFcm();
        $fake->result = FcmSender::PRUNE;
        $this->app->instance(FcmSender::class, $fake);

        $user = User::factory()->create();
        DeviceToken::factory()->create(['user_id' => $user->id, 'token' => 'dead', 'platform' => 'ios']);
        $event = $this->event();

        (new SendEventPush($event->id, $user->id, 'event_departure', [
            'type' => 'event_departure', 'eventKey' => $event->key, 'title' => 'Gig',
        ]))->handle($fake);

        $this->assertDatabaseMissing('device_tokens', ['token' => 'dead']);
        $this->assertDatabaseMissing('push_notification_log', [
            'event_id' => $event->id, 'user_id' => $user->id, 'type' => 'event_departure',
        ]);
    }
}
