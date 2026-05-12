<?php

namespace Tests\Feature\Api\Mobile;

use App\Models\Bands;
use App\Models\Bookings;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BookingContractHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_fetch_contract_history(): void
    {
        $envelopeId = 'env-test-123';

        // Avoid hitting the live PandaDoc OAuth refresh endpoint during the test.
        config([
            'services.pandadoc.access_token'     => 'fake-token',
            'services.pandadoc.token_expires_at' => time() + 3600,
        ]);

        // Fake PandaDoc audit-trail HTTP response (used by Signable::auditTrail()).
        Http::fake([
            "https://api.pandadoc.com/public/v2/documents/{$envelopeId}/audit-trail" => Http::response([
                'results' => [
                    [
                        'id'           => 'e1',
                        'action'       => 6,
                        'date_created' => '2026-01-15T12:34:56Z',
                        'user'         => [
                            'id'    => 'u1',
                            'email' => 'signer@example.com',
                        ],
                        'ip_address'   => '127.0.0.1',
                        'reason'       => null,
                        'status'       => 'completed',
                    ],
                ],
            ], 200),
        ]);

        $user = User::factory()->create();
        $band = Bands::factory()->create();
        $band->owners()->create(['user_id' => $user->id]);

        $booking = Bookings::factory()->create([
            'band_id' => $band->id,
            'status'  => 'draft',
        ]);

        $booking->contract()->create([
            'author_id'   => $user->id,
            'status'      => 'sent',
            'envelope_id' => $envelopeId,
        ]);

        $token = $user->createToken('test-device')->plainTextToken;

        $response = $this->withToken($token)
            ->getJson("/api/mobile/contracts/{$envelopeId}/history");

        $response->assertOk()
            ->assertJsonStructure(['history'])
            ->assertJsonPath('history.results.0.id', 'e1')
            ->assertJsonPath('history.results.0.action', 'Document Sent')
            ->assertJsonPath('history.results.0.user_email', 'signer@example.com');
    }

    public function test_user_from_different_band_cannot_fetch_history(): void
    {
        $envelopeId = 'pd-cross-band-test';

        config([
            'services.pandadoc.access_token'     => 'fake-token',
            'services.pandadoc.token_expires_at' => time() + 3600,
        ]);

        $bandA = Bands::factory()->create();
        $bandB = Bands::factory()->create();

        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $bandA->owners()->create(['user_id' => $userA->id]);
        $bandB->owners()->create(['user_id' => $userB->id]);

        $bookingA = Bookings::factory()->create([
            'band_id' => $bandA->id,
            'status'  => 'draft',
        ]);
        $bookingA->contract()->create([
            'author_id'   => $userA->id,
            'envelope_id' => $envelopeId,
            'status'      => 'pending',
        ]);

        $token = $userB->createToken('test-device')->plainTextToken;

        $response = $this->withToken($token)
            ->getJson("/api/mobile/contracts/{$envelopeId}/history");

        $response->assertForbidden();
    }

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->getJson('/api/mobile/contracts/anything/history')
            ->assertUnauthorized();
    }

    public function test_unknown_envelope_id_returns_404(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-device')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/mobile/contracts/does-not-exist/history')
            ->assertNotFound();
    }
}
