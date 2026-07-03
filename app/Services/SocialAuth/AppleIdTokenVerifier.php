<?php

namespace App\Services\SocialAuth;

class AppleIdTokenVerifier extends AbstractIdTokenVerifier
{
    protected function provider(): string
    {
        return 'apple';
    }

    protected function jwksUrl(): string
    {
        return 'https://appleid.apple.com/auth/keys';
    }

    protected function allowedIssuers(): array
    {
        return ['https://appleid.apple.com'];
    }

    protected function allowedAudiences(): array
    {
        return config('services.apple.allowed_client_ids', []);
    }

    protected function toProfile(object $claims): SocialProfile
    {
        // Apple id_tokens never carry a display name; SocialAuthService falls
        // back to the email local-part when creating the user.
        return new SocialProfile(
            provider: 'apple',
            providerId: $claims->sub,
            email: $claims->email,
            name: null,
            avatarUrl: null,
        );
    }
}
