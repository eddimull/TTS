<?php

namespace App\Handler;

use Illuminate\Http\Request;
use Spatie\WebhookClient\Exceptions\InvalidConfig;
use Spatie\WebhookClient\WebhookConfig;
use Spatie\WebhookClient\SignatureValidator\SignatureValidator;

class PandadocSignature implements SignatureValidator
{
    public function isValid(Request $request, WebhookConfig $config): bool
    {

        $signature = $request[$config->signatureHeaderName];

        if (! $signature)
        {
            return false;
        }

        $signingSecret = $config->signingSecret;

        if (empty($signingSecret))
        {
            throw InvalidConfig::signingSecretNotSet();
        }

        $computedSignature = hash_hmac('sha256', $request->getContent(), $signingSecret);

        return hash_equals($computedSignature, $signature);
    }
}
