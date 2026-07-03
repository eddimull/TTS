<?php

namespace Tests\Unit\SocialAuth;

use App\Services\SocialAuth\FacebookAccessTokenVerifier;
use App\Services\SocialAuth\InvalidSocialTokenException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FacebookAccessTokenVerifierTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['services.facebook.client_secret' => 'shhh']);
    }

    public function test_valid_token_yields_profile(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'id'      => 'fb-1',
                'name'    => 'Face Book',
                'email'   => 'fb@example.com',
                'picture' => ['data' => ['url' => 'https://example.com/p.png']],
            ]),
        ]);

        $profile = app(FacebookAccessTokenVerifier::class)->verify('fb-token');

        $this->assertSame('facebook', $profile->provider);
        $this->assertSame('fb-1', $profile->providerId);
        $this->assertSame('fb@example.com', $profile->email);
        $this->assertSame('Face Book', $profile->name);
        $this->assertSame('https://example.com/p.png', $profile->avatarUrl);
    }

    public function test_graph_error_is_rejected(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['error' => ['message' => 'bad token']], 400)]);

        $this->expectException(InvalidSocialTokenException::class);
        app(FacebookAccessTokenVerifier::class)->verify('bad');
    }

    public function test_account_without_email_is_rejected(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['id' => 'fb-1', 'name' => 'No Mail'])]);

        $this->expectException(InvalidSocialTokenException::class);
        app(FacebookAccessTokenVerifier::class)->verify('fb-token');
    }
}
