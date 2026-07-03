<?php

namespace Tests\Feature\Api\Mobile;

use App\Models\SocialAccount;
use App\Models\User;
use App\Services\SocialAuth\InvalidSocialTokenException;
use App\Services\SocialAuth\SocialProfile;
use App\Services\SocialAuth\SocialTokenVerifier;
use App\Services\SocialAuth\SocialTokenVerifierManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SocialLoginTest extends TestCase
{
    use RefreshDatabase;

    private function fakeVerifier(SocialProfile $profile): void
    {
        $verifier = new class($profile) implements SocialTokenVerifier {
            public function __construct(private readonly SocialProfile $profile) {}

            public function verify(string $token): SocialProfile
            {
                if ($token === 'bad-token') {
                    throw new InvalidSocialTokenException('Could not verify your google sign-in.');
                }

                return $this->profile;
            }
        };

        $this->mock(SocialTokenVerifierManager::class)
            ->shouldReceive('for')
            ->andReturn($verifier);
    }

    public function test_new_user_is_created_and_receives_standard_envelope(): void
    {
        $this->fakeVerifier(new SocialProfile('google', 'g-9', 'social@example.com', 'Social Sam', null));

        $response = $this->postJson('/api/mobile/auth/social', [
            'provider'    => 'google',
            'token'       => 'good-token',
            'device_name' => 'tts_bandmate_app',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['token', 'user' => ['id', 'name', 'email', 'avatar_url'], 'bands']);

        $this->assertSame('Social Sam', User::where('email', 'social@example.com')->first()->name);
        $this->assertSame(1, SocialAccount::count());
    }

    public function test_existing_email_logs_into_existing_account(): void
    {
        $user = User::factory()->create(['email' => 'existing@example.com']);
        $this->fakeVerifier(new SocialProfile('google', 'g-9', 'existing@example.com', 'Whoever', null));

        $response = $this->postJson('/api/mobile/auth/social', [
            'provider'    => 'google',
            'token'       => 'good-token',
            'device_name' => 'tts_bandmate_app',
        ]);

        $response->assertOk()->assertJsonPath('user.id', $user->id);
        $this->assertSame(1, User::where('email', 'existing@example.com')->count());
    }

    public function test_invalid_provider_token_is_422(): void
    {
        $this->fakeVerifier(new SocialProfile('google', 'g-9', 'x@example.com', null, null));

        $this->postJson('/api/mobile/auth/social', [
            'provider'    => 'google',
            'token'       => 'bad-token',
            'device_name' => 'tts_bandmate_app',
        ])->assertStatus(422)->assertJsonValidationErrors('token');
    }

    public function test_unknown_provider_is_422(): void
    {
        $this->postJson('/api/mobile/auth/social', [
            'provider'    => 'myspace',
            'token'       => 't',
            'device_name' => 'tts_bandmate_app',
        ])->assertStatus(422)->assertJsonValidationErrors('provider');
    }
}
