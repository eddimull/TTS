<?php

namespace Tests\Feature\Api\Mobile;

use App\Models\BandMembers;
use App\Models\BandOwners;
use App\Models\BandSubs;
use App\Models\Bands;
use App\Models\Charts;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MobileChartsAggregateTest extends TestCase
{
    use RefreshDatabase;

    private function makeBand(string $name, bool $isPersonal = false): Bands
    {
        return Bands::create([
            'name'        => $name,
            'site_name'   => str()->slug($name) . '-' . uniqid(),
            'is_personal' => $isPersonal,
        ]);
    }

    public function test_returns_charts_from_all_user_bands(): void
    {
        $user = User::factory()->create();

        $bandA = $this->makeBand('Band A');
        $bandB = $this->makeBand('Band B');
        BandOwners::create(['user_id' => $user->id, 'band_id' => $bandA->id]);
        BandOwners::create(['user_id' => $user->id, 'band_id' => $bandB->id]);

        Charts::create(['band_id' => $bandA->id, 'title' => 'A1']);
        Charts::create(['band_id' => $bandA->id, 'title' => 'A2']);
        Charts::create(['band_id' => $bandA->id, 'title' => 'A3']);
        Charts::create(['band_id' => $bandB->id, 'title' => 'B1']);
        Charts::create(['band_id' => $bandB->id, 'title' => 'B2']);
        Charts::create(['band_id' => $bandB->id, 'title' => 'B3']);

        $token = $user->createToken('test')->plainTextToken;
        $response = $this->withToken($token)->getJson('/api/mobile/charts');

        $response->assertOk();
        $charts = $response->json('charts');
        $this->assertCount(6, $charts);
    }

    public function test_excludes_charts_from_bands_user_is_not_in(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $userBand = $this->makeBand('Mine');
        $otherBand = $this->makeBand('Theirs');
        BandOwners::create(['user_id' => $user->id, 'band_id' => $userBand->id]);
        BandOwners::create(['user_id' => $other->id, 'band_id' => $otherBand->id]);

        Charts::create(['band_id' => $userBand->id, 'title' => 'Mine 1']);
        Charts::create(['band_id' => $otherBand->id, 'title' => 'Theirs 1']);

        $token = $user->createToken('test')->plainTextToken;
        $response = $this->withToken($token)->getJson('/api/mobile/charts');

        $response->assertOk();
        $titles = collect($response->json('charts'))->pluck('title');
        $this->assertContains('Mine 1', $titles);
        $this->assertNotContains('Theirs 1', $titles);
    }

    public function test_band_member_also_sees_charts(): void
    {
        $user = User::factory()->create();
        $band = $this->makeBand('Member Band');
        BandMembers::create(['user_id' => $user->id, 'band_id' => $band->id]);
        Charts::create(['band_id' => $band->id, 'title' => 'Member Chart']);

        $token = $user->createToken('test')->plainTextToken;
        $response = $this->withToken($token)->getJson('/api/mobile/charts');

        $response->assertOk();
        $titles = collect($response->json('charts'))->pluck('title');
        $this->assertContains('Member Chart', $titles);
    }

    public function test_band_sub_also_sees_charts(): void
    {
        $user = User::factory()->create();
        $band = $this->makeBand('Sub Band');
        BandSubs::create(['user_id' => $user->id, 'band_id' => $band->id]);
        Charts::create(['band_id' => $band->id, 'title' => 'Sub Chart']);

        $token = $user->createToken('test')->plainTextToken;
        $response = $this->withToken($token)->getJson('/api/mobile/charts');

        $response->assertOk();
        $titles = collect($response->json('charts'))->pluck('title');
        $this->assertContains('Sub Chart', $titles);
    }

    public function test_each_chart_includes_band_block(): void
    {
        $user = User::factory()->create();
        $band = $this->makeBand('My Band');
        BandOwners::create(['user_id' => $user->id, 'band_id' => $band->id]);

        Charts::create(['band_id' => $band->id, 'title' => 'Stardust']);

        $token = $user->createToken('test')->plainTextToken;
        $response = $this->withToken($token)->getJson('/api/mobile/charts');

        $response->assertOk();
        $chart = $response->json('charts.0');

        $this->assertArrayHasKey('band', $chart);
        $this->assertSame($band->id, $chart['band']['id']);
        $this->assertSame('My Band', $chart['band']['name']);
        $this->assertFalse($chart['band']['is_personal']);
        $this->assertArrayHasKey('logo_url', $chart['band']);
    }

    public function test_includes_personal_band_charts_with_is_personal_true(): void
    {
        $user = User::factory()->create();
        $personal = $this->makeBand("{$user->name}'s Band", isPersonal: true);
        BandOwners::create(['user_id' => $user->id, 'band_id' => $personal->id]);

        Charts::create(['band_id' => $personal->id, 'title' => 'Solo Etude']);

        $token = $user->createToken('test')->plainTextToken;
        $response = $this->withToken($token)->getJson('/api/mobile/charts');

        $response->assertOk();
        $chart = $response->json('charts.0');
        $this->assertTrue($chart['band']['is_personal']);
    }

    public function test_unauthenticated_user_returns_401(): void
    {
        $response = $this->getJson('/api/mobile/charts');
        $response->assertStatus(401);
    }

    public function test_returns_empty_array_when_user_has_no_bands(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;
        $response = $this->withToken($token)->getJson('/api/mobile/charts');
        $response->assertOk();
        $this->assertSame([], $response->json('charts'));
    }
}
