<?php

namespace Tests\Feature\Api\Mobile;

use App\Models\BandOwners;
use App\Models\Bands;
use App\Models\BandSubs;
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

    public function test_returns_empty_array_when_user_has_no_bands(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withToken($token)->getJson('/api/mobile/me/bookings');

        $response->assertOk();
        $this->assertSame([], $response->json('bookings'));
    }

    public function test_excludes_bookings_from_bands_user_is_only_a_sub_for(): void
    {
        $user = User::factory()->create();
        $myBand = Bands::create([
            'name' => 'Mine', 'site_name' => 'mine-' . uniqid(), 'is_personal' => false,
        ]);
        $subBand = Bands::create([
            'name' => 'Sub For', 'site_name' => 'sub-' . uniqid(), 'is_personal' => false,
        ]);
        BandOwners::create(['user_id' => $user->id, 'band_id' => $myBand->id]);
        BandSubs::create(['user_id' => $user->id, 'band_id' => $subBand->id]);

        Bookings::factory()->create([
            'name' => 'Mine Gig', 'date' => '2026-06-01', 'band_id' => $myBand->id,
        ]);
        Bookings::factory()->create([
            'name' => 'Sub Band Gig', 'date' => '2026-06-02', 'band_id' => $subBand->id,
        ]);

        $token = $user->createToken('test')->plainTextToken;
        $response = $this->withToken($token)->getJson('/api/mobile/me/bookings');
        $response->assertOk();

        $names = collect($response->json('bookings'))->pluck('name')->all();
        $this->assertContains('Mine Gig', $names);
        $this->assertNotContains('Sub Band Gig', $names,
            'Bookings carry money/contract info subs should not see');
    }

    public function test_filters_by_status_upcoming_and_year(): void
    {
        $user = User::factory()->create();
        $band = Bands::create([
            'name' => 'B', 'site_name' => 'b-' . uniqid(), 'is_personal' => false,
        ]);
        BandOwners::create(['user_id' => $user->id, 'band_id' => $band->id]);

        // Past confirmed booking (should be excluded by upcoming=1).
        Bookings::factory()->create([
            'name' => 'Past', 'date' => '2024-01-01', 'band_id' => $band->id, 'status' => 'confirmed',
        ]);
        // Future confirmed booking in 2026 (should match all three filters).
        Bookings::factory()->create([
            'name' => 'Future Confirmed 2026', 'date' => '2026-12-31', 'band_id' => $band->id, 'status' => 'confirmed',
        ]);
        // Future pending booking in 2026 (should be excluded by status=confirmed).
        Bookings::factory()->create([
            'name' => 'Future Pending 2026', 'date' => '2026-11-01', 'band_id' => $band->id, 'status' => 'pending',
        ]);
        // Future confirmed booking in 2027 (should be excluded by year=2026).
        Bookings::factory()->create([
            'name' => 'Future Confirmed 2027', 'date' => '2027-06-01', 'band_id' => $band->id, 'status' => 'confirmed',
        ]);

        $token = $user->createToken('test')->plainTextToken;
        $response = $this->withToken($token)->getJson(
            '/api/mobile/me/bookings?status=confirmed&upcoming=1&year=2026'
        );
        $response->assertOk();

        $names = collect($response->json('bookings'))->pluck('name')->all();
        $this->assertSame(['Future Confirmed 2026'], $names);
    }

    public function test_from_param_filters_to_on_or_after(): void
    {
        $user = User::factory()->create();
        $band = Bands::create([
            'name' => 'Band', 'site_name' => 'b-' . uniqid(), 'is_personal' => false,
        ]);
        BandOwners::create(['user_id' => $user->id, 'band_id' => $band->id]);

        Bookings::factory()->for($band, 'band')->create(['date' => '2026-01-15', 'name' => 'Old']);
        Bookings::factory()->for($band, 'band')->create(['date' => '2026-06-01', 'name' => 'New']);

        $response = $this->actingAs($user)
            ->getJson('/api/mobile/me/bookings?from=2026-05-01');

        $response->assertOk();
        $names = collect($response->json('bookings'))->pluck('name')->all();
        $this->assertEqualsCanonicalizing(['New'], $names);
    }

    public function test_to_param_filters_to_on_or_before(): void
    {
        $user = User::factory()->create();
        $band = Bands::create([
            'name' => 'Band', 'site_name' => 'b-' . uniqid(), 'is_personal' => false,
        ]);
        BandOwners::create(['user_id' => $user->id, 'band_id' => $band->id]);

        Bookings::factory()->for($band, 'band')->create(['date' => '2026-01-15', 'name' => 'Old']);
        Bookings::factory()->for($band, 'band')->create(['date' => '2026-06-01', 'name' => 'New']);

        $response = $this->actingAs($user)
            ->getJson('/api/mobile/me/bookings?to=2026-05-01');

        $response->assertOk();
        $names = collect($response->json('bookings'))->pluck('name')->all();
        $this->assertEqualsCanonicalizing(['Old'], $names);
    }

    public function test_from_and_to_together_narrow_to_inclusive_range(): void
    {
        $user = User::factory()->create();
        $band = Bands::create([
            'name' => 'Band', 'site_name' => 'b-' . uniqid(), 'is_personal' => false,
        ]);
        BandOwners::create(['user_id' => $user->id, 'band_id' => $band->id]);

        Bookings::factory()->for($band, 'band')->create(['date' => '2026-01-01', 'name' => 'Before']);
        Bookings::factory()->for($band, 'band')->create(['date' => '2026-03-15', 'name' => 'Inside']);
        Bookings::factory()->for($band, 'band')->create(['date' => '2026-12-01', 'name' => 'After']);

        $response = $this->actingAs($user)
            ->getJson('/api/mobile/me/bookings?from=2026-02-01&to=2026-05-01');

        $response->assertOk();
        $names = collect($response->json('bookings'))->pluck('name')->all();
        $this->assertEqualsCanonicalizing(['Inside'], $names);
    }

    public function test_from_after_to_returns_422(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->getJson('/api/mobile/me/bookings?from=2026-06-01&to=2026-05-01');

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('from');
    }

    public function test_no_params_still_returns_all_bookings(): void
    {
        $user = User::factory()->create();
        $band = Bands::create([
            'name' => 'Band', 'site_name' => 'b-' . uniqid(), 'is_personal' => false,
        ]);
        BandOwners::create(['user_id' => $user->id, 'band_id' => $band->id]);

        Bookings::factory()->for($band, 'band')->create(['date' => '2020-01-01']);
        Bookings::factory()->for($band, 'band')->create(['date' => '2026-06-01']);
        Bookings::factory()->for($band, 'band')->create(['date' => '2030-12-01']);

        $response = $this->actingAs($user)->getJson('/api/mobile/me/bookings');

        $response->assertOk();
        $this->assertCount(3, $response->json('bookings'));
    }
}
