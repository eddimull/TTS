<?php

namespace Tests\Feature\Api\Mobile;

use App\Models\EventTypes;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GoSoloTokenTest extends TestCase
{
    use RefreshDatabase;

    public function test_go_solo_returns_token_that_can_create_a_personal_booking(): void
    {
        $user = User::factory()->create();
        // Stale token: user currently owns nothing, so no write:bookings.
        $stale = $user->createToken('iphone', ['mobile'])->plainTextToken;

        $response = $this->withToken($stale)
            ->postJson('/api/mobile/bands/solo')
            ->assertStatus(201)
            ->assertJsonStructure(['token', 'bands']);

        $newToken = $response->json('token');
        $personalBandId = collect($response->json('bands'))
            ->firstWhere('is_personal', true)['id'];

        // The freshly returned token can create a booking on the new personal band.
        $this->withToken($newToken)
            ->withHeaders(['X-Band-ID' => $personalBandId])
            ->postJson("/api/mobile/bands/{$personalBandId}/bookings", [
                'name'          => 'My Solo Gig',
                'event_type_id' => EventTypes::first()->id,
                'events'        => [
                    [
                        'title'      => 'My Solo Gig',
                        'date'       => now()->addDays(5)->format('Y-m-d'),
                        'start_time' => '19:00',
                    ],
                ],
            ])
            ->assertStatus(201);
    }

    public function test_go_solo_idempotent_call_also_returns_a_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('iphone', ['mobile'])->plainTextToken;

        // First call creates the personal band (consumes/deletes the token).
        $this->withToken($token)->postJson('/api/mobile/bands/solo')->assertStatus(201);

        // Second call (idempotent path) with a fresh token must STILL return a
        // token + bands, not just bands.
        $callToken = $user->createToken('iphone', ['mobile'])->plainTextToken;

        $this->withToken($callToken)
            ->postJson('/api/mobile/bands/solo')
            ->assertOk()
            ->assertJsonStructure(['token', 'bands']);
    }
}
