<?php

namespace Tests\Feature\Api\Mobile;

use App\Models\Bands;
use App\Models\BandOwners;
use App\Models\User;
use App\Services\Mobile\TokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TokenRefreshTest extends TestCase
{
    use RefreshDatabase;

    public function test_reissue_for_current_device_mints_token_with_current_abilities(): void
    {
        $user = User::factory()->create();
        $band = Bands::factory()->create();
        BandOwners::create(['user_id' => $user->id, 'band_id' => $band->id]);

        // Old token issued with NO abilities (simulates a stale token).
        $user->createToken('iphone', ['mobile']);
        $current = $user->tokens()->first();

        $plain = app(TokenService::class)->reissueForCurrentDevice($user, $current);

        $this->assertIsString($plain);
        $newToken = $user->tokens()->latest('id')->first();
        $this->assertContains('write:bookings', $newToken->abilities);
        $this->assertSame('iphone', $newToken->name);

        // Old token is gone.
        $this->assertNull($user->tokens()->find($current->id));
    }

    public function test_reissue_falls_back_to_mobile_device_name_when_current_null(): void
    {
        $user = User::factory()->create();

        $plain = app(TokenService::class)->reissueForCurrentDevice($user, null);

        $this->assertIsString($plain);
        $this->assertSame('mobile', $user->tokens()->latest('id')->first()->name);
    }

    public function test_refresh_requires_authentication(): void
    {
        $this->postJson('/api/mobile/token/refresh')->assertUnauthorized();
    }

    public function test_refresh_reissues_token_with_current_abilities(): void
    {
        $user = User::factory()->create();
        $band = Bands::factory()->create();
        BandOwners::create(['user_id' => $user->id, 'band_id' => $band->id]);

        // Issue a stale token (mobile only — no write:bookings).
        $stale = $user->createToken('iphone', ['mobile'])->plainTextToken;

        $response = $this->withToken($stale)
            ->postJson('/api/mobile/token/refresh')
            ->assertOk()
            ->assertJsonStructure(['token', 'user', 'bands']);

        $newToken = $user->tokens()->latest('id')->first();
        $this->assertContains('write:bookings', $newToken->abilities);
        $this->assertSame('iphone', $newToken->name);
    }

    public function test_refresh_does_not_grant_access_to_a_band_the_user_is_not_in(): void
    {
        $user = User::factory()->create();
        $stale = $user->createToken('iphone', ['mobile'])->plainTextToken;

        // A band the user has NO relationship with.
        $otherBand = Bands::factory()->create();

        // Refresh the token.
        $newToken = $this->withToken($stale)
            ->postJson('/api/mobile/token/refresh')
            ->assertOk()
            ->json('token');

        // The refreshed token must not carry write:bookings for ANY band — the
        // user belongs to none, so buildAbilities() emits no band abilities.
        // This proves refresh re-syncs abilities to reality, not just that the
        // membership gate blocks.
        $this->assertNotContains(
            'write:bookings',
            $user->tokens()->latest('id')->first()->abilities,
        );

        // Even refreshed, the user cannot create a booking on a band they're not in.
        $this->withToken($newToken)
            ->withHeaders(['X-Band-ID' => $otherBand->id])
            ->postJson("/api/mobile/bands/{$otherBand->id}/bookings", [
                'name' => 'Sneaky Gig',
                'date' => now()->addDays(5)->format('Y-m-d'),
            ])
            ->assertStatus(403);
    }
}
