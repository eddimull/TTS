<?php

namespace App\Jobs;

use App\Models\DeviceToken;
use App\Models\PushNotificationLog;
use App\Services\Push\FcmSender;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendEventPush implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @param array<string,string> $payload
     */
    public function __construct(
        public int $eventId,
        public int $userId,
        public string $type,
        public array $payload,
    ) {}

    public function handle(FcmSender $fcm): void
    {
        $tokens = DeviceToken::where('user_id', $this->userId)->get();
        $anyDelivered = false;

        foreach ($tokens as $deviceToken) {
            $result = $fcm->sendData($deviceToken->token, $this->payload);
            if ($result === FcmSender::PRUNE) {
                $deviceToken->delete();
            } elseif ($result === FcmSender::DELIVERED) {
                $anyDelivered = true;
            }
        }

        if ($anyDelivered) {
            PushNotificationLog::firstOrCreate(
                ['event_id' => $this->eventId, 'user_id' => $this->userId, 'type' => $this->type],
                ['sent_at' => now()],
            );
        }
    }
}
