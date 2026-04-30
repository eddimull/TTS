<?php

namespace Tests\Feature\Api\Mobile;

use App\Models\BandOwners;
use App\Models\Bands;
use App\Models\User;
use App\Services\Mobile\TokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TokenServiceFormatBandsTest extends TestCase
{
    use RefreshDatabase;

    public function test_format_bands_includes_is_personal_and_logo_url(): void
    {
        $user = User::factory()->create();

        $regular = Bands::create([
            'name' => 'The Real Band',
            'site_name' => 'the-real-band',
            'logo' => 'logos/real.png',
            'is_personal' => false,
        ]);
        $personal = Bands::create([
            'name' => "{$user->name}'s Band",
            'site_name' => 'eddies-band',
            'logo' => '',
            'is_personal' => true,
        ]);

        BandOwners::create(['user_id' => $user->id, 'band_id' => $regular->id]);
        BandOwners::create(['user_id' => $user->id, 'band_id' => $personal->id]);

        $service = app(TokenService::class);
        $formatted = $service->formatBands($user);

        $this->assertCount(2, $formatted);

        $regularRow = collect($formatted)->firstWhere('id', $regular->id);
        $this->assertSame('The Real Band', $regularRow['name']);
        $this->assertFalse($regularRow['is_personal']);
        $this->assertNotNull($regularRow['logo_url']);

        $personalRow = collect($formatted)->firstWhere('id', $personal->id);
        $this->assertTrue($personalRow['is_personal']);
        $this->assertNull($personalRow['logo_url']);
    }
}
