<?php

namespace Tests\Feature\Api\Mobile;

use App\Models\Bands;
use App\Models\Lodging;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LodgingsTest extends TestCase
{
    use RefreshDatabase;

    private function createOwnerWithBand(): array
    {
        $user = User::factory()->create();
        $band = Bands::factory()->create();
        $band->owners()->create(['user_id' => $user->id]);
        $token = $user->createToken('test-device')->plainTextToken;

        return compact('user', 'band', 'token');
    }

    public function test_index_requires_band_header(): void
    {
        ['band' => $band, 'token' => $token] = $this->createOwnerWithBand();
        $this->withToken($token)
            ->getJson("/api/mobile/bands/{$band->id}/lodgings")
            ->assertStatus(422);
    }

    public function test_index_returns_403_for_non_member(): void
    {
        ['band' => $band] = $this->createOwnerWithBand();
        $stranger = User::factory()->create();
        $token = $stranger->createToken('test-device')->plainTextToken;
        $this->withToken($token)
            ->withHeaders(['X-Band-ID' => $band->id])
            ->getJson("/api/mobile/bands/{$band->id}/lodgings")
            ->assertStatus(403);
    }

    public function test_index_lists_band_lodgings_upcoming_first(): void
    {
        ['band' => $band, 'token' => $token] = $this->createOwnerWithBand();
        Lodging::factory()->create([
            'band_id' => $band->id, 'name' => 'Later Hotel',
            'check_in_at' => now()->addDays(20), 'check_out_at' => now()->addDays(21),
        ]);
        Lodging::factory()->create([
            'band_id' => $band->id, 'name' => 'Sooner Hotel',
            'check_in_at' => now()->addDays(5), 'check_out_at' => now()->addDays(6),
        ]);
        // Another band's lodging must not leak.
        Lodging::factory()->create(['name' => 'Other Band Hotel']);

        $response = $this->withToken($token)
            ->withHeaders(['X-Band-ID' => $band->id])
            ->getJson("/api/mobile/bands/{$band->id}/lodgings")
            ->assertOk()
            ->json();

        $names = array_column($response['lodgings'], 'name');
        $this->assertSame(['Sooner Hotel', 'Later Hotel'], $names);
        $this->assertTrue($response['can_write']);
    }

    public function test_store_creates_lodging_with_rooms(): void
    {
        ['band' => $band, 'token' => $token] = $this->createOwnerWithBand();

        $checkIn  = now()->addDays(10)->format('Y-m-d') . ' 15:00:00';
        $checkOut = now()->addDays(12)->format('Y-m-d') . ' 11:00:00';

        $response = $this->withToken($token)
            ->withHeaders(['X-Band-ID' => $band->id])
            ->postJson("/api/mobile/bands/{$band->id}/lodgings", [
                'name'         => 'Hampton Inn',
                'address'      => '123 Main St',
                'latitude'     => 30.4,
                'longitude'    => -91.1,
                'check_in_at'  => $checkIn,
                'check_out_at' => $checkOut,
                'notes'        => 'Park in back',
                'rooms'        => [
                    ['label' => 'King', 'confirmation_number' => 'ABC123'],
                    ['label' => 'Double Queen', 'notes' => 'near elevator'],
                ],
            ])
            ->assertStatus(201)
            ->json();

        $this->assertSame('Hampton Inn', $response['lodging']['name']);
        $this->assertCount(2, $response['lodging']['rooms']);
        $this->assertDatabaseHas('lodging_rooms', ['label' => 'King', 'confirmation_number' => 'ABC123']);
    }

    public function test_update_syncs_rooms_by_id(): void
    {
        ['band' => $band, 'token' => $token] = $this->createOwnerWithBand();
        $lodging = Lodging::factory()->create(['band_id' => $band->id]);
        $keep   = $lodging->rooms()->create(['label' => 'King', 'sort_order' => 0]);
        $lodging->rooms()->create(['label' => 'Doomed', 'sort_order' => 1]);

        $response = $this->withToken($token)
            ->withHeaders(['X-Band-ID' => $band->id])
            ->patchJson("/api/mobile/bands/{$band->id}/lodgings/{$lodging->id}", [
                'rooms' => [
                    ['id' => $keep->id, 'label' => 'King Renamed'],
                    ['label' => 'Brand New'],
                ],
            ])
            ->assertOk()
            ->json();

        $labels = array_column($response['lodging']['rooms'], 'label');
        $this->assertSame(['King Renamed', 'Brand New'], $labels);
        $this->assertDatabaseMissing('lodging_rooms', ['label' => 'Doomed']);
    }

    public function test_member_without_write_cannot_store(): void
    {
        ['band' => $band] = $this->createOwnerWithBand();
        $member = User::factory()->create();
        $band->members()->create(['user_id' => $member->id]);
        $token = $member->createToken('test-device')->plainTextToken;

        $this->withToken($token)
            ->withHeaders(['X-Band-ID' => $band->id])
            ->postJson("/api/mobile/bands/{$band->id}/lodgings", [
                'name'         => 'Nope',
                'check_in_at'  => now()->addDay()->format('Y-m-d H:i:s'),
                'check_out_at' => now()->addDays(2)->format('Y-m-d H:i:s'),
            ])
            ->assertStatus(403);
    }

    public function test_destroy_soft_deletes(): void
    {
        ['band' => $band, 'token' => $token] = $this->createOwnerWithBand();
        $lodging = Lodging::factory()->create(['band_id' => $band->id]);

        $this->withToken($token)
            ->withHeaders(['X-Band-ID' => $band->id])
            ->deleteJson("/api/mobile/bands/{$band->id}/lodgings/{$lodging->id}")
            ->assertOk();

        $this->assertSoftDeleted('lodgings', ['id' => $lodging->id]);
    }
}
