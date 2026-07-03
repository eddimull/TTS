<?php

namespace App\Services\SocialAuth;

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Verifies an OIDC id_token against the provider's published JWKS.
 * Signature + exp are checked by firebase/php-jwt; iss and aud here.
 */
abstract class AbstractIdTokenVerifier implements SocialTokenVerifier
{
    abstract protected function provider(): string;

    abstract protected function jwksUrl(): string;

    /** @return string[] */
    abstract protected function allowedIssuers(): array;

    /** @return string[] */
    abstract protected function allowedAudiences(): array;

    abstract protected function toProfile(object $claims): SocialProfile;

    public function verify(string $token): SocialProfile
    {
        try {
            $jwks = Cache::remember(
                "social-jwks:{$this->provider()}",
                now()->addHour(),
                fn () => Http::timeout(10)->get($this->jwksUrl())->throw()->json(),
            );
            $claims = JWT::decode($token, JWK::parseKeySet($jwks));
        } catch (\Throwable) {
            throw new InvalidSocialTokenException("Could not verify your {$this->provider()} sign-in.");
        }

        $audiences = (array) ($claims->aud ?? []);
        if (!in_array($claims->iss ?? '', $this->allowedIssuers(), true)
            || array_intersect($audiences, $this->allowedAudiences()) === []) {
            throw new InvalidSocialTokenException("Could not verify your {$this->provider()} sign-in.");
        }

        if (empty($claims->email)) {
            throw new InvalidSocialTokenException(
                "Your {$this->provider()} account did not share an email address."
            );
        }

        if (empty($claims->sub)) {
            throw new InvalidSocialTokenException("Could not verify your {$this->provider()} sign-in.");
        }

        return $this->toProfile($claims);
    }
}
