<?php

namespace Tests\Feature\Api\Mobile;

use App\Models\BandOwners;
use App\Models\Bands;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_mobile_token_endpoint_returns_token_and_bands_on_valid_credentials(): void
    {
        $user = User::factory()->create(['password' => bcrypt('password')]);
        $band = Bands::factory()->create();
        BandOwners::factory()->create(['user_id' => $user->id, 'band_id' => $band->id]);

        $response = $this->postJson('/api/mobile/auth/token', [
            'email' => $user->email,
            'password' => 'password',
            'device_name' => 'iPhone 15',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'token',
                'user' => ['id', 'name', 'email'],
                'bands' => [['id', 'name', 'is_owner']],
            ]);

        $this->assertNotEmpty($response->json('token'));
        $this->assertEquals($user->id, $response->json('user.id'));
        $this->assertCount(1, $response->json('bands'));
        $this->assertTrue($response->json('bands.0.is_owner'));
    }

    public function test_mobile_token_endpoint_returns_422_on_invalid_credentials(): void
    {
        $user = User::factory()->create(['password' => bcrypt('password')]);

        $response = $this->postJson('/api/mobile/auth/token', [
            'email' => $user->email,
            'password' => 'wrong-password',
            'device_name' => 'iPhone 15',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_mobile_me_endpoint_returns_user_and_bands(): void
    {
        $user = User::factory()->create();
        $band = Bands::factory()->create();
        BandOwners::factory()->create(['user_id' => $user->id, 'band_id' => $band->id]);

        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withToken($token)->getJson('/api/mobile/auth/me');

        $response->assertOk()
            ->assertJsonStructure([
                'user' => ['id', 'name', 'email'],
                'bands' => [['id', 'name', 'is_owner']],
            ]);

        $this->assertEquals($user->id, $response->json('user.id'));
        $this->assertCount(1, $response->json('bands'));
    }

    public function test_mobile_me_endpoint_returns_401_without_token(): void
    {
        $this->getJson('/api/mobile/auth/me')->assertUnauthorized();
    }

    public function test_mobile_logout_revokes_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)->deleteJson('/api/mobile/auth/token')->assertOk();

        // Verify the token is deleted from the database
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }
}
