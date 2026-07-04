<?php

namespace App\Services\SocialAuth;

interface SocialTokenVerifier
{
    /** @throws InvalidSocialTokenException */
    public function verify(string $token): SocialProfile;
}
