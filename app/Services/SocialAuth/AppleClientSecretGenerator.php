<?php

namespace App\Services\SocialAuth;

use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;

/**
 * Mints the Sign in with Apple client secret (an ES256 JWT) from the
 * non-expiring .p8 key, so no human ever regenerates it by hand.
 * Cached well inside the JWT's own lifetime.
 */
class AppleClientSecretGenerator
{
    public function isConfigured(): bool
    {
        return (bool) (config('services.apple.private_key')
            && config('services.apple.key_id')
            && config('services.apple.team_id')
            && config('services.apple.client_id'));
    }

    public function generate(): string
    {
        $cacheKey = 'apple-client-secret:'.config('services.apple.key_id');

        return Cache::remember($cacheKey, now()->addHours(12), function () {
            $now = time();

            return JWT::encode([
                'iss' => config('services.apple.team_id'),
                'iat' => $now,
                'exp' => $now + 86400, // 1 day — regenerated from cache long before
                'aud' => 'https://appleid.apple.com',
                'sub' => config('services.apple.client_id'),
            ], base64_decode(config('services.apple.private_key')), 'ES256', config('services.apple.key_id'));
        });
    }
}
