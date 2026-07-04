<?php

namespace Tests\Unit\SocialAuth;

use App\Services\SocialAuth\AppleClientSecretGenerator;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class AppleClientSecretGeneratorTest extends TestCase
{
    private string $privateKeyPem = '';

    private string $publicKeyPem = '';

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();

        // Real EC P-256 key pair, matching Apple's ES256 requirement.
        $res = openssl_pkey_new([
            'curve_name'       => 'prime256v1',
            'private_key_type' => OPENSSL_KEYTYPE_EC,
        ]);
        openssl_pkey_export($res, $this->privateKeyPem);
        $this->publicKeyPem = openssl_pkey_get_details($res)['key'];

        config([
            'services.apple.private_key' => base64_encode($this->privateKeyPem),
            'services.apple.key_id'      => 'ABC123KEYID',
            'services.apple.team_id'     => 'TEAM123456',
            'services.apple.client_id'   => 'band.tts.bandmate.service',
        ]);
    }

    private function base64UrlDecode(string $segment): string
    {
        return base64_decode(strtr($segment, '-_', '+/'));
    }

    public function test_generate_returns_three_segment_jwt_with_expected_header_and_payload(): void
    {
        $token = (new AppleClientSecretGenerator())->generate();

        $segments = explode('.', $token);
        $this->assertCount(3, $segments);

        $header = json_decode($this->base64UrlDecode($segments[0]), true);
        $this->assertSame('ES256', $header['alg']);
        $this->assertSame('ABC123KEYID', $header['kid']);

        $payload = json_decode($this->base64UrlDecode($segments[1]), true);
        $this->assertSame('TEAM123456', $payload['iss']);
        $this->assertSame('band.tts.bandmate.service', $payload['sub']);
        $this->assertSame('https://appleid.apple.com', $payload['aud']);
        $this->assertGreaterThan($payload['iat'], $payload['exp']);
    }

    public function test_generated_token_signature_validates_against_public_key(): void
    {
        $token = (new AppleClientSecretGenerator())->generate();

        $decoded = JWT::decode($token, new Key($this->publicKeyPem, 'ES256'));

        $this->assertSame('TEAM123456', $decoded->iss);
        $this->assertSame('band.tts.bandmate.service', $decoded->sub);
    }

    public function test_generate_is_cached_and_returns_identical_token(): void
    {
        $generator = new AppleClientSecretGenerator();

        $first = $generator->generate();
        sleep(1);
        $second = $generator->generate();

        $this->assertSame($first, $second);
    }

    public function test_is_configured_true_when_all_present_false_when_private_key_empty(): void
    {
        $generator = new AppleClientSecretGenerator();
        $this->assertTrue($generator->isConfigured());

        config(['services.apple.private_key' => null]);
        $this->assertFalse($generator->isConfigured());
    }

    public function test_rotating_key_id_self_heals_instead_of_serving_stale_cached_kid(): void
    {
        $generator = new AppleClientSecretGenerator();

        $first = $generator->generate();

        // Simulate rotating the .p8 key: only the kid changes, the signing
        // key material can stay the same for the purposes of this test.
        config(['services.apple.key_id' => 'NEWKEYID999']);

        $second = $generator->generate();

        $header = json_decode($this->base64UrlDecode(explode('.', $second)[0]), true);

        $this->assertSame('NEWKEYID999', $header['kid'], 'Rotated key id should be reflected immediately, not served from the old cache entry.');
        $this->assertNotSame($first, $second);
    }
}
