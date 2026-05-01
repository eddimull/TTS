<?php

namespace Tests\Feature\Api\Mobile;

use App\Models\BandOwners;
use App\Models\Bands;
use App\Models\Bookings;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MeBookingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_request_is_rejected(): void
    {
        $response = $this->getJson('/api/mobile/me/bookings');
        $response->assertStatus(401);
    }

    public function test_returns_bookings_across_all_users_bands(): void
    {
        $user = User::factory()->create();

        $bandA = Bands::create([
            'name' => 'Band A', 'site_name' => 'band-a-' . uniqid(), 'is_personal' => false,
        ]);
        $bandB = Bands::create([
            'name' => 'Band B', 'site_name' => 'band-b-' . uniqid(), 'is_personal' => false,
        ]);
        $personal = Bands::create([
            'name' => "{$user->name}'s Band", 'site_name' => 'eddies-band-' . uniqid(), 'is_personal' => true,
        ]);

        foreach ([$bandA, $bandB, $personal] as $b) {
            BandOwners::create(['user_id' => $user->id, 'band_id' => $b->id]);
        }

        Bookings::factory()->create(['name' => 'A Gig', 'date' => '2026-06-01', 'band_id' => $bandA->id]);
        Bookings::factory()->create(['name' => 'B Gig', 'date' => '2026-06-02', 'band_id' => $bandB->id]);
        Bookings::factory()->create(['name' => 'Church', 'date' => '2026-06-03', 'band_id' => $personal->id]);

        $token = $user->createToken('test')->plainTextToken;
        $response = $this->withToken($token)->getJson('/api/mobile/me/bookings');
        $response->assertOk();

        $bookings = $response->json('bookings');
        $this->assertCount(3, $bookings);

        $names = collect($bookings)->pluck('name')->all();
        $this->assertContains('A Gig', $names);
        $this->assertContains('B Gig', $names);
        $this->assertContains('Church', $names);

        $church = collect($bookings)->firstWhere('name', 'Church');
        $this->assertTrue($church['band']['is_personal']);
    }

    public function test_excludes_bookings_from_bands_user_does_not_belong_to(): void
    {
        $user = User::factory()->create();
        $myBand = Bands::create([
            'name' => 'Mine', 'site_name' => 'mine-' . uniqid(), 'is_personal' => false,
        ]);
        $otherBand = Bands::create([
            'name' => 'Other', 'site_name' => 'other-' . uniqid(), 'is_personal' => false,
        ]);
        BandOwners::create(['user_id' => $user->id, 'band_id' => $myBand->id]);

        Bookings::factory()->create(['name' => 'Mine Gig', 'date' => '2026-06-01', 'band_id' => $myBand->id]);
        Bookings::factory()->create(['name' => 'Other Gig', 'date' => '2026-06-02', 'band_id' => $otherBand->id]);

        $token = $user->createToken('test')->plainTextToken;
        $response = $this->withToken($token)->getJson('/api/mobile/me/bookings');
        $response->assertOk();

        $bookings = $response->json('bookings');
        $names = collect($bookings)->pluck('name')->all();
        $this->assertContains('Mine Gig', $names);
        $this->assertNotContains('Other Gig', $names);
    }
}
