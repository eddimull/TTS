<?php

namespace App\Services\SocialAuth;

class SocialTokenVerifierManager
{
    public function for(string $provider): SocialTokenVerifier
    {
        return match ($provider) {
            'google'   => app(GoogleIdTokenVerifier::class),
            'apple'    => app(AppleIdTokenVerifier::class),
            'facebook' => app(FacebookAccessTokenVerifier::class),
        };
    }
}
