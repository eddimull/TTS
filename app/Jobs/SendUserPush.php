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

/**
 * Generic per-user push send. Callers supply the full data payload
 * (contract: type/title/body + routing keys) and a dedupe key that is
 * unique per logical send — the (user_id, dedupe_key) log row guarantees
 * at-most-one recorded delivery per user per logical send.
 */
class SendUserPush implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @param array<string,string> $data
     */
    public function __construct(
        public int $userId,
        public array $data,
        public string $dedupeKey,
        public bool $alert = false,
    ) {}

    public function handle(FcmSender $fcm): void
    {
        $tokens = DeviceToken::where('user_id', $this->userId)->get();
        $anyDelivered = false;

        foreach ($tokens as $deviceToken) {
            $result = $this->alert
                ? $fcm->sendAlert(
                    $deviceToken->token,
                    (string) ($this->data['title'] ?? ''),
                    (string) ($this->data['body'] ?? ''),
                    $this->data,
                )
                : $fcm->sendData($deviceToken->token, $this->data);

            if ($result === FcmSender::PRUNE) {
                $deviceToken->delete();
            } elseif ($result === FcmSender::DELIVERED) {
                $anyDelivered = true;
            }
        }

        if ($anyDelivered) {
            PushNotificationLog::firstOrCreate(
                ['user_id' => $this->userId, 'dedupe_key' => $this->dedupeKey],
                ['type' => (string) ($this->data['type'] ?? 'generic'), 'sent_at' => now()],
            );
        }
    }
}
