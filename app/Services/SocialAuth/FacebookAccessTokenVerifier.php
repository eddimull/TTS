<?php

namespace App\Services\SocialAuth;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Facebook uses opaque access tokens, not id_tokens — validate by calling the
 * Graph API. appsecret_proof binds the call to OUR app secret, so a token
 * issued to a different app fails (enable "Require App Secret" in the FB app).
 */
class FacebookAccessTokenVerifier implements SocialTokenVerifier
{
    public function verify(string $token): SocialProfile
    {
        try {
            $response = Http::timeout(10)->get('https://graph.facebook.com/v21.0/me', [
                'fields'           => 'id,name,email,picture.type(large)',
                'access_token'     => $token,
                'appsecret_proof'  => hash_hmac('sha256', $token, config('services.facebook.client_secret', '')),
            ]);
        } catch (ConnectionException) {
            // Timeout/DNS failure talking to Graph API — treat like any other
            // unverifiable token (422), not a 500, matching the pattern used
            // in the id-token verifiers' catch (\Throwable) blocks.
            throw new InvalidSocialTokenException('Could not verify your facebook sign-in.');
        }

        if ($response->failed() || !$response->json('id')) {
            throw new InvalidSocialTokenException('Could not verify your facebook sign-in.');
        }

        $email = $response->json('email');
        if (!$email) {
            throw new InvalidSocialTokenException(
                'Your Facebook account has no email address. Please sign up with email instead.'
            );
        }

        return new SocialProfile(
            provider: 'facebook',
            providerId: (string) $response->json('id'),
            email: $email,
            name: $response->json('name'),
            avatarUrl: $response->json('picture.data.url'),
        );
    }
}
