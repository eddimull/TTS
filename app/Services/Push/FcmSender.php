<?php

namespace App\Services\Push;

use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Exception\Messaging\InvalidArgument;
use Kreait\Firebase\Exception\Messaging\InvalidMessage;
use Kreait\Firebase\Exception\Messaging\NotFound;
use Kreait\Firebase\Exception\MessagingException;
use Kreait\Firebase\Messaging\CloudMessage;

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
}
