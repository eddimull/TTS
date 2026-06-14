<?php

namespace Tests\Unit\Push;

use App\Models\DeviceToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeviceTokenTest extends TestCase
{
    use RefreshDatabase;

    public function test_belongs_to_a_user(): void
    {
        $user = User::factory()->create();
        $token = DeviceToken::factory()->create(['user_id' => $user->id]);

        $this->assertTrue($token->user->is($user));
    }

    public function test_token_is_unique(): void
    {
        DeviceToken::factory()->create(['token' => 'abc']);
        $this->expectException(\Illuminate\Database\QueryException::class);
        DeviceToken::factory()->create(['token' => 'abc']);
    }
}
