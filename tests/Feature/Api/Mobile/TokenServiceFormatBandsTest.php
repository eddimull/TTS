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
            'logo' => '/images/default.png',
            'is_personal' => true,
        ]);

        BandOwners::create(['user_id' => $user->id, 'band_id' => $regular->id]);
        BandOwners::create(['user_id' => $user->id, 'band_id' => $personal->id]);

        $service = app(TokenService::class);
        $formatted = $service->formatBands($user);

        $this->assertCount(2, $formatted);

        // Storage-relative uploaded logo -> /storage/<path>
        $regularRow = collect($formatted)->firstWhere('id', $regular->id);
        $this->assertSame('The Real Band', $regularRow['name']);
        $this->assertFalse($regularRow['is_personal']);
        $this->assertSame(asset('storage/logos/real.png'), $regularRow['logo_url']);

        // Personal band uses the schema default '/images/default.png',
        // which is a public-root path, NOT a storage path.
        $personalRow = collect($formatted)->firstWhere('id', $personal->id);
        $this->assertTrue($personalRow['is_personal']);
        $this->assertSame(asset('images/default.png'), $personalRow['logo_url']);
    }

    public function test_format_bands_resolves_leading_slash_logo_as_public_path(): void
    {
        $user = User::factory()->create();

        $band = Bands::create([
            'name' => 'Custom Logo Band',
            'site_name' => 'custom-logo-band',
            'logo' => '/images/custom.png',
            'is_personal' => false,
        ]);

        BandOwners::create(['user_id' => $user->id, 'band_id' => $band->id]);

        $service = app(TokenService::class);
        $formatted = $service->formatBands($user);

        $row = collect($formatted)->firstWhere('id', $band->id);

        // Public-root path: no 'storage/' prefix, no doubled slashes.
        $this->assertSame(asset('images/custom.png'), $row['logo_url']);
        $this->assertStringNotContainsString('storage/', $row['logo_url']);
        $this->assertStringNotContainsString('//images', $row['logo_url']);
    }

    public function test_resolve_logo_url_helper_handles_all_branches(): void
    {
        $this->assertNull(TokenService::resolveLogoUrl(null));
        $this->assertNull(TokenService::resolveLogoUrl(''));
        $this->assertSame(
            asset('images/default.png'),
            TokenService::resolveLogoUrl('/images/default.png')
        );
        $this->assertSame(
            asset('storage/logos/real.png'),
            TokenService::resolveLogoUrl('logos/real.png')
        );
    }
}
