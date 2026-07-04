<?php

namespace App\Services\SocialAuth;

class GoogleIdTokenVerifier extends AbstractIdTokenVerifier
{
    protected function provider(): string
    {
        return 'google';
    }

    protected function jwksUrl(): string
    {
        return 'https://www.googleapis.com/oauth2/v3/certs';
    }

    protected function allowedIssuers(): array
    {
        return ['https://accounts.google.com', 'accounts.google.com'];
    }

    protected function allowedAudiences(): array
    {
        return config('services.google.allowed_client_ids', []);
    }

    protected function toProfile(object $claims): SocialProfile
    {
        return new SocialProfile(
            provider: 'google',
            providerId: $claims->sub,
            email: $claims->email,
            name: $claims->name ?? null,
            avatarUrl: $claims->picture ?? null,
        );
    }
}
