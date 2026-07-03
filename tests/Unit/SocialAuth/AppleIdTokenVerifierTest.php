<?php

namespace Tests\Unit\SocialAuth;

use App\Services\SocialAuth\AppleIdTokenVerifier;
use App\Services\SocialAuth\InvalidSocialTokenException;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AppleIdTokenVerifierTest extends TestCase
{
    private string $privateKey = '';

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config(['services.apple.allowed_client_ids' => ['band.tts.bandmate']]);

        $res = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($res, $this->privateKey);
        $details = openssl_pkey_get_details($res);

        $b64url = fn (string $bin) => rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
        Http::fake([
            'https://appleid.apple.com/auth/keys' => Http::response([
                'keys' => [[
                    'kty' => 'RSA',
                    'kid' => 'test-key',
                    'use' => 'sig',
                    'alg' => 'RS256',
                    'n'   => $b64url($details['rsa']['n']),
                    'e'   => $b64url($details['rsa']['e']),
                ]],
            ]),
        ]);
    }

    private function makeToken(array $overrides = []): string
    {
        $claims = array_merge([
            'iss'   => 'https://appleid.apple.com',
            'aud'   => 'band.tts.bandmate',
            'sub'   => 'apple-user-1',
            'email' => 'apple-user@example.com',
            'iat'   => time(),
            'exp'   => time() + 300,
        ], $overrides);

        return JWT::encode($claims, $this->privateKey, 'RS256', 'test-key');
    }

    public function test_valid_token_yields_profile(): void
    {
        $profile = app(AppleIdTokenVerifier::class)->verify($this->makeToken());

        $this->assertSame('apple', $profile->provider);
        $this->assertSame('apple-user-1', $profile->providerId);
        $this->assertSame('apple-user@example.com', $profile->email);
        $this->assertNull($profile->name);
    }

    public function test_wrong_audience_is_rejected(): void
    {
        $this->expectException(InvalidSocialTokenException::class);
        app(AppleIdTokenVerifier::class)->verify($this->makeToken(['aud' => 'some.other.app']));
    }

    public function test_expired_token_is_rejected(): void
    {
        $this->expectException(InvalidSocialTokenException::class);
        app(AppleIdTokenVerifier::class)->verify($this->makeToken(['exp' => time() - 10, 'iat' => time() - 600]));
    }

    public function test_garbage_token_is_rejected(): void
    {
        $this->expectException(InvalidSocialTokenException::class);
        app(AppleIdTokenVerifier::class)->verify('not-a-jwt');
    }

    public function test_token_without_sub_is_rejected(): void
    {
        $this->expectException(InvalidSocialTokenException::class);
        $claims = [
            'iss'   => 'https://appleid.apple.com',
            'aud'   => 'band.tts.bandmate',
            'email' => 'apple-user@example.com',
            'iat'   => time(),
            'exp'   => time() + 300,
        ];
        $tokenWithoutSub = JWT::encode($claims, $this->privateKey, 'RS256', 'test-key');
        app(AppleIdTokenVerifier::class)->verify($tokenWithoutSub);
    }
}
