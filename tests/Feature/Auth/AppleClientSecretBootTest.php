<?php

namespace Tests\Feature\Auth;

use App\Providers\AppServiceProvider;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class AppleClientSecretBootTest extends TestCase
{
    private string $privateKeyPem = '';

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();

        $res = openssl_pkey_new([
            'curve_name'       => 'prime256v1',
            'private_key_type' => OPENSSL_KEYTYPE_EC,
        ]);
        openssl_pkey_export($res, $this->privateKeyPem);

        config([
            'services.apple.key_id'    => 'ABC123KEYID',
            'services.apple.team_id'   => 'TEAM123456',
            'services.apple.client_id' => 'band.tts.bandmate.service',
        ]);
    }

    /**
     * Re-run the AppServiceProvider boot logic the same way Laravel does at
     * request start, so the client_secret config override is exercised.
     */
    private function rebootProvider(): void
    {
        (new AppServiceProvider($this->app))->boot();
    }

    public function test_valid_key_material_overrides_client_secret_with_a_jwt(): void
    {
        config([
            'services.apple.private_key'   => base64_encode($this->privateKeyPem),
            'services.apple.client_secret' => 'static-env-value',
        ]);

        $this->rebootProvider();

        $secret = config('services.apple.client_secret');
        $this->assertCount(3, explode('.', $secret));
        $this->assertNotSame('static-env-value', $secret);
    }

    public function test_corrupted_key_does_not_throw_and_retains_static_secret(): void
    {
        config([
            'services.apple.private_key'   => base64_encode('not-a-key'),
            'services.apple.client_secret' => 'static-env-value',
        ]);

        $this->rebootProvider();

        // Generation failed internally but was swallowed; static value stands.
        $this->assertSame('static-env-value', config('services.apple.client_secret'));
    }
}
