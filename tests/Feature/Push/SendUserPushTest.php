<?php

namespace Tests\Feature\Push;

use App\Jobs\SendUserPush;
use App\Models\DeviceToken;
use App\Models\PushNotificationLog;
use App\Models\User;
use App\Services\Push\FcmSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class SendUserPushTest extends TestCase
{
    use RefreshDatabase;

    private function fakeSender(string $result): FcmSender
    {
        $sender = Mockery::mock(FcmSender::class);
        $sender->shouldReceive('sendData')->andReturn($result)->byDefault();
        $sender->shouldReceive('sendAlert')->andReturn($result)->byDefault();
        return $sender;
    }

    public function test_delivered_send_writes_log_row_keyed_on_dedupe_key(): void
    {
        $user = User::factory()->create();
        DeviceToken::create(['user_id' => $user->id, 'token' => 'tok-1', 'platform' => 'android']);

        $job = new SendUserPush($user->id, ['type' => 'rehearsal_cancelled', 'title' => 'T', 'body' => 'B'], 'rehearsal:1:cancelled:111');
        $job->handle($this->fakeSender(FcmSender::DELIVERED));

        $this->assertDatabaseHas('push_notification_log', [
            'user_id'    => $user->id,
            'dedupe_key' => 'rehearsal:1:cancelled:111',
            'type'       => 'rehearsal_cancelled',
        ]);
    }

    public function test_alert_flag_uses_send_alert_with_title_and_body(): void
    {
        $user = User::factory()->create();
        DeviceToken::create(['user_id' => $user->id, 'token' => 'tok-1', 'platform' => 'android']);

        $sender = Mockery::mock(FcmSender::class);
        $sender->shouldReceive('sendAlert')
            ->once()
            ->with('tok-1', 'Rehearsal cancelled', 'Tuesday practice', Mockery::type('array'))
            ->andReturn(FcmSender::DELIVERED);

        $data = ['type' => 'rehearsal_cancelled', 'title' => 'Rehearsal cancelled', 'body' => 'Tuesday practice'];
        (new SendUserPush($user->id, $data, 'k1', alert: true))->handle($sender);
    }

    public function test_data_only_by_default(): void
    {
        $user = User::factory()->create();
        DeviceToken::create(['user_id' => $user->id, 'token' => 'tok-1', 'platform' => 'android']);

        $sender = Mockery::mock(FcmSender::class);
        $sender->shouldReceive('sendData')->once()->andReturn(FcmSender::DELIVERED);
        $sender->shouldNotReceive('sendAlert');

        (new SendUserPush($user->id, ['type' => 'event_reminder_8h', 'title' => 'T'], 'k2'))->handle($sender);
    }

    public function test_pruned_token_is_deleted_and_no_log_written(): void
    {
        $user = User::factory()->create();
        DeviceToken::create(['user_id' => $user->id, 'token' => 'dead', 'platform' => 'ios']);

        (new SendUserPush($user->id, ['type' => 't'], 'k3'))->handle($this->fakeSender(FcmSender::PRUNE));

        $this->assertDatabaseMissing('device_tokens', ['token' => 'dead']);
        $this->assertDatabaseMissing('push_notification_log', ['dedupe_key' => 'k3']);
    }

    public function test_duplicate_dedupe_key_does_not_create_second_row(): void
    {
        $user = User::factory()->create();
        DeviceToken::create(['user_id' => $user->id, 'token' => 'tok-1', 'platform' => 'android']);

        $data = ['type' => 'rehearsal_cancelled', 'title' => 'T', 'body' => 'B'];
        (new SendUserPush($user->id, $data, 'dup-key'))->handle($this->fakeSender(FcmSender::DELIVERED));
        (new SendUserPush($user->id, $data, 'dup-key'))->handle($this->fakeSender(FcmSender::DELIVERED));

        $this->assertSame(1, PushNotificationLog::where('dedupe_key', 'dup-key')->count());
    }
}
