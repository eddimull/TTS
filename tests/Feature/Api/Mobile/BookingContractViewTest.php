<?php

namespace Tests\Feature\Api\Mobile;

use App\Models\Bands;
use App\Models\Bookings;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BookingContractViewTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_member_can_view_contract_pdf(): void
    {
        Storage::fake('s3');

        $user = User::factory()->create();
        $band = Bands::factory()->create();
        $band->owners()->create(['user_id' => $user->id]);

        $booking = Bookings::factory()->create([
            'band_id' => $band->id,
            'status'  => 'draft',
        ]);

        $filePath = "contracts/bookings/{$booking->id}/contract.pdf";
        Storage::disk('s3')->put($filePath, '%PDF-1.4 fake pdf bytes');

        $booking->contract()->create([
            'author_id' => $user->id,
            'status'    => 'completed',
            'asset_url' => $filePath,
        ]);

        $token = $user->createToken('test-device')->plainTextToken;

        $response = $this->withToken($token)
            ->withHeaders(['X-Band-ID' => $band->id])
            ->get("/api/mobile/bands/{$band->id}/bookings/{$booking->id}/contract/view");

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
    }

    public function test_unauthenticated_request_returns_401(): void
    {
        $band = Bands::factory()->create();
        $booking = Bookings::factory()->create([
            'band_id' => $band->id,
            'status'  => 'draft',
        ]);

        $this->getJson("/api/mobile/bands/{$band->id}/bookings/{$booking->id}/contract/view")
            ->assertUnauthorized();
    }

    public function test_missing_contract_returns_404(): void
    {
        Storage::fake('s3');

        $user = User::factory()->create();
        $band = Bands::factory()->create();
        $band->owners()->create(['user_id' => $user->id]);

        $booking = Bookings::factory()->create([
            'band_id' => $band->id,
            'status'  => 'draft',
        ]);

        // Contract exists but has no asset_url
        $booking->contract()->create([
            'author_id' => $user->id,
            'status'    => 'pending',
            'asset_url' => null,
        ]);

        $token = $user->createToken('test-device')->plainTextToken;

        $this->withToken($token)
            ->withHeaders(['X-Band-ID' => $band->id])
            ->get("/api/mobile/bands/{$band->id}/bookings/{$booking->id}/contract/view")
            ->assertNotFound();
    }
}
