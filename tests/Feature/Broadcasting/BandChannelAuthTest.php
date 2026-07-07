<?php

namespace Tests\Feature\Broadcasting;

use App\Models\Bands;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class BandChannelAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The pusher driver signs auth responses locally (no network) — give
        // it deterministic creds regardless of the surrounding .env.
        config([
            'broadcasting.default'                    => 'pusher',
            'broadcasting.connections.pusher.key'     => 'test-key',
            'broadcasting.connections.pusher.secret'  => 'test-secret',
            'broadcasting.connections.pusher.app_id'  => 'test-app',
        ]);

        // routes/channels.php registers Broadcast::channel(...) callbacks
        // against whichever driver is default at app-boot time (phpunit.xml
        // pins BROADCAST_DRIVER=null). BroadcastManager::driver() caches one
        // instance per driver name, so switching the default above resolves
        // to a *new*, channel-less "pusher" broadcaster unless we purge the
        // cache and replay the channel registrations against it.
        Broadcast::purge();
        require base_path('routes/channels.php');
    }

    private function authAgainstChannel(User $user, int $bandId): TestResponse
    {
        $token = $user->createToken('test-device')->plainTextToken;

        return $this->withToken($token)->postJson('/broadcasting/auth', [
            'socket_id'    => '123.456',
            'channel_name' => 'private-band.' . $bandId,
        ]);
    }

    public function test_band_owner_can_subscribe_to_their_band_channel(): void
    {
        $user = User::factory()->create();
        $band = Bands::factory()->create();
        $band->owners()->create(['user_id' => $user->id]);

        $response = $this->authAgainstChannel($user, $band->id);

        $response->assertOk();
        $this->assertIsString($response->json('auth'));
    }

    public function test_web_session_user_can_subscribe_via_stateful_auth(): void
    {
        $user = User::factory()->create();
        $band = Bands::factory()->create();
        $band->owners()->create(['user_id' => $user->id]);

        // Browser flow: session auth + stateful referer (no Bearer token).
        // Sanctum only consults the web guard for stateful first-party hosts.
        $response = $this->actingAs($user)
            ->withHeader('Referer', config('app.url'))
            ->postJson('/broadcasting/auth', [
                'socket_id'    => '123.456',
                'channel_name' => 'private-band.' . $band->id,
            ]);

        $response->assertOk();
        $this->assertIsString($response->json('auth'));
    }

    public function test_non_member_is_rejected(): void
    {
        $user = User::factory()->create();
        $band = Bands::factory()->create(); // user has no relation to it

        $this->authAgainstChannel($user, $band->id)->assertForbidden();
    }
}
