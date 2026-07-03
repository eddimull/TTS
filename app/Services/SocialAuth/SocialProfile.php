<?php

namespace App\Services\SocialAuth;

readonly class SocialProfile
{
    public function __construct(
        public string $provider,
        public string $providerId,
        public string $email,
        public ?string $name,
        public ?string $avatarUrl,
    ) {}
}
