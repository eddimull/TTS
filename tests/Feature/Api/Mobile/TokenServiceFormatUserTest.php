<?php

namespace Tests\Feature\Api\Mobile;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TokenServiceFormatUserTest extends TestCase
{
    use RefreshDatabase;

    public function test_format_user_via_auth_me_includes_avatar_url_key(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withToken($token)->getJson('/api/mobile/auth/me');
        $response->assertOk();

        $userJson = $response->json('user');
        $this->assertArrayHasKey('avatar_url', $userJson,
            'Mobile clients require avatar_url to render personal-gig chips');
    }

    public function test_user_without_uploaded_avatar_gets_null_avatar_url(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withToken($token)->getJson('/api/mobile/auth/me');

        $userJson = $response->json('user');
        $this->assertNull($userJson['avatar_url'],
            'A user with no uploaded avatar should report null so mobile falls back to initials');
    }
}
