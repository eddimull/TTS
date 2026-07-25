<?php

namespace Tests\Unit\Push;

use App\Services\Push\FcmSender;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Messaging\CloudMessage;
use Mockery;
use PHPUnit\Framework\TestCase;

class FcmSenderTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @param callable(array):bool $assert receives the serialized message */
    private function messagingExpecting(callable $assert): Messaging
    {
        $messaging = Mockery::mock(Messaging::class);
        $messaging->shouldReceive('send')
            ->once()
            ->with(Mockery::on(function (CloudMessage $message) use ($assert) {
                return $assert(json_decode(json_encode($message), true));
            }));

        return $messaging;
    }

    public function test_send_alert_with_android_tag_sets_notification_tag(): void
    {
        $sender = new FcmSender($this->messagingExpecting(
            fn (array $m) => ($m['android']['notification']['tag'] ?? null) === 'chat_5'
                && ($m['android']['notification']['channel_id'] ?? null) === 'band_updates'
        ));

        $result = $sender->sendAlert('tok', 'Title', 'Body', ['type' => 'chat_message'], 'chat_5');

        $this->assertSame(FcmSender::DELIVERED, $result);
    }

    public function test_send_alert_without_android_tag_omits_tag(): void
    {
        $sender = new FcmSender($this->messagingExpecting(
            fn (array $m) => !array_key_exists('tag', $m['android']['notification'] ?? [])
                && ($m['android']['notification']['channel_id'] ?? null) === 'band_updates'
        ));

        $result = $sender->sendAlert('tok', 'Title', 'Body', ['type' => 'chat_message']);

        $this->assertSame(FcmSender::DELIVERED, $result);
    }
}
