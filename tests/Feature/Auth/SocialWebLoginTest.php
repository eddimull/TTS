<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

class SocialWebLoginTest extends TestCase
{
    use RefreshDatabase;

    private function mockSocialiteUser(): void
    {
        $socialiteUser = (new SocialiteUser())->map([
            'id'     => 'g-web-1',
            'email'  => 'weblogin@example.com',
            'name'   => 'Web Person',
            'avatar' => null,
        ]);
        $socialiteUser->user = ['email_verified' => true];

        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('user')->andReturn($socialiteUser);
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
    }

    public function test_redirect_route_sends_user_to_provider(): void
    {
        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('redirect')->andReturn(redirect('https://accounts.google.com/o/oauth2/auth'));
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $this->get('/auth/google/redirect')->assertRedirect();
    }

    public function test_callback_creates_user_and_logs_in(): void
    {
        $this->mockSocialiteUser();

        $response = $this->get('/auth/google/callback?code=abc&state=xyz');

        $response->assertRedirect();
        $this->assertAuthenticated();
        $user = User::where('email', 'weblogin@example.com')->first();
        $this->assertNotNull($user);
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_callback_links_existing_user_by_email(): void
    {
        $existing = User::factory()->create(['email' => 'weblogin@example.com']);
        $this->mockSocialiteUser();

        $this->get('/auth/google/callback?code=abc&state=xyz');

        $this->assertAuthenticatedAs($existing);
    }

    public function test_provider_failure_redirects_to_login_with_error(): void
    {
        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('user')->andThrow(new \Exception('provider blew up'));
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $this->get('/auth/google/callback?code=abc&state=xyz')
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');
    }

    public function test_unknown_provider_404s(): void
    {
        $this->get('/auth/myspace/redirect')->assertNotFound();
    }

    public function test_callback_rejects_unverified_email(): void
    {
        $socialiteUser = (new SocialiteUser())->map([
            'id'     => 'g-web-2',
            'email'  => 'unverified@example.com',
            'name'   => 'Unverified Person',
            'avatar' => null,
        ]);
        $socialiteUser->user = ['email_verified' => false];

        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('user')->andReturn($socialiteUser);
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $response = $this->get('/auth/google/callback?code=abc&state=xyz');

        $response->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->assertNull(User::where('email', 'unverified@example.com')->first());
    }
}
