<?php

namespace App\Services\Push;

use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Exception\Messaging\InvalidArgument;
use Kreait\Firebase\Exception\Messaging\InvalidMessage;
use Kreait\Firebase\Exception\Messaging\NotFound;
use Kreait\Firebase\Exception\MessagingException;
use Kreait\Firebase\Messaging\AndroidConfig;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;

class FcmSender
{
    public const DELIVERED = 'delivered';
    public const PRUNE = 'prune';      // token is dead; caller should delete it
    public const TRANSIENT = 'transient';

    public function __construct(private Messaging $messaging) {}

    /**
     * Send a data-only message to one token.
     * @param array<string,string> $data
     */
    public function sendData(string $token, array $data): string
    {
        try {
            $message = CloudMessage::new()->withToken($token)->withData($data);
            $this->messaging->send($message);
            return self::DELIVERED;
        } catch (NotFound | InvalidMessage | InvalidArgument) {
            // Permanently bad token: unregistered (404) or malformed/invalid
            // registration token (400). It will never become valid — prune it.
            return self::PRUNE;
        } catch (MessagingException $e) {
            Log::warning('FcmSender transient error', ['error' => $e->getMessage()]);
            return self::TRANSIENT;
        }
    }

    /**
     * Send a notification+data (hybrid) message to one token. The OS renders
     * the notification when the app is backgrounded/terminated; the data map
     * still carries the full payload contract for in-app routing.
     * @param array<string,string> $data
     */
    public function sendAlert(string $token, string $title, string $body, array $data = []): string
    {
        try {
            $message = CloudMessage::new()
                ->withToken($token)
                ->withNotification(Notification::create($title, $body))
                ->withData($data)
                ->withAndroidConfig(AndroidConfig::fromArray([
                    'notification' => ['channel_id' => 'band_updates'],
                ]));
            $this->messaging->send($message);
            return self::DELIVERED;
        } catch (NotFound | InvalidMessage | InvalidArgument) {
            return self::PRUNE;
        } catch (MessagingException $e) {
            Log::warning('FcmSender transient error', ['error' => $e->getMessage()]);
            return self::TRANSIENT;
        }
    }
}
