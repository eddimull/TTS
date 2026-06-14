<?php

namespace Tests\Feature\Push;

use App\Models\DeviceToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DeviceRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_requires_auth(): void
    {
        $this->postJson('/api/mobile/devices', ['token' => 't', 'platform' => 'ios'])
            ->assertUnauthorized();
    }

    public function test_register_creates_token_for_user(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/mobile/devices', ['token' => 'tok-abc', 'platform' => 'ios'])
            ->assertOk();

        $this->assertDatabaseHas('device_tokens', [
            'user_id' => $user->id, 'token' => 'tok-abc', 'platform' => 'ios',
        ]);
    }

    public function test_register_is_idempotent_by_token(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/mobile/devices', ['token' => 'tok-abc', 'platform' => 'ios'])->assertOk();
        $this->postJson('/api/mobile/devices', ['token' => 'tok-abc', 'platform' => 'android'])->assertOk();

        $this->assertSame(1, DeviceToken::where('token', 'tok-abc')->count());
        $this->assertDatabaseHas('device_tokens', ['token' => 'tok-abc', 'platform' => 'android']);
    }

    public function test_validation_rejects_bad_platform(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $this->postJson('/api/mobile/devices', ['token' => 't', 'platform' => 'windows'])
            ->assertStatus(422);
    }

    public function test_destroy_removes_only_callers_token(): void
    {
        $me = User::factory()->create();
        $other = User::factory()->create();
        DeviceToken::factory()->create(['user_id' => $other->id, 'token' => 'theirs', 'platform' => 'ios']);
        DeviceToken::factory()->create(['user_id' => $me->id, 'token' => 'mine', 'platform' => 'ios']);

        Sanctum::actingAs($me);
        $this->deleteJson('/api/mobile/devices/mine')->assertOk();

        $this->assertDatabaseMissing('device_tokens', ['token' => 'mine']);
        $this->assertDatabaseHas('device_tokens', ['token' => 'theirs']);
    }
}
