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
        $user->createToken('iphone', ['mobile'])->plainTextToken;
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
}
