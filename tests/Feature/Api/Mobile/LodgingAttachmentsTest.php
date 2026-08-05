<?php
namespace Tests\Feature\Api\Mobile;

use App\Models\Bands;
use App\Models\Lodging;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LodgingAttachmentsTest extends TestCase
{
    use RefreshDatabase;

    private function createOwnerWithLodging(): array
    {
        $user = User::factory()->create();
        $band = Bands::factory()->create();
        $band->owners()->create(['user_id' => $user->id]);
        $lodging = Lodging::factory()->create(['band_id' => $band->id]);
        $token = $user->createToken('test-device')->plainTextToken;
        return compact('user', 'band', 'lodging', 'token');
    }

    public function test_upload_stores_file_and_returns_attachment(): void
    {
        Storage::fake(config('filesystems.default'));
        ['band' => $band, 'lodging' => $lodging, 'token' => $token] = $this->createOwnerWithLodging();

        $response = $this->withToken($token)
            ->withHeaders(['X-Band-ID' => $band->id])
            ->post("/api/mobile/bands/{$band->id}/lodgings/{$lodging->id}/attachments", [
                'file' => UploadedFile::fake()->image('confirmation.jpg'),
            ])
            ->assertStatus(201)
            ->json();

        $this->assertSame('confirmation.jpg', $response['attachment']['filename']);
        $this->assertStringContainsString('/api/mobile/lodging-attachments/', $response['attachment']['url']);
        $this->assertDatabaseHas('lodging_attachments', ['lodging_id' => $lodging->id]);
        Storage::disk(config('filesystems.default'))
            ->assertExists($lodging->attachments()->first()->stored_filename);
    }

    public function test_serve_returns_bytes_for_member_and_403_for_stranger(): void
    {
        Storage::fake(config('filesystems.default'));
        ['band' => $band, 'lodging' => $lodging, 'token' => $token] = $this->createOwnerWithLodging();

        $this->withToken($token)
            ->withHeaders(['X-Band-ID' => $band->id])
            ->post("/api/mobile/bands/{$band->id}/lodgings/{$lodging->id}/attachments", [
                'file' => UploadedFile::fake()->image('map.jpg'),
            ])->assertStatus(201);

        $attachment = $lodging->attachments()->first();

        $this->withToken($token)
            ->get("/api/mobile/lodging-attachments/{$attachment->id}")
            ->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg');

        // Laravel's Sanctum RequestGuard memoizes the resolved user on the
        // guard instance, which survives across simulated requests within a
        // single test method (the app container isn't rebooted between
        // ->get() calls). Without forgetting guards here, this second
        // request would silently resolve back to the first (owner) token's
        // user instead of the stranger's. See framework
        // Auth\RequestGuard::user() caching + AuthManager guard memoization.
        Auth::forgetGuards();

        $stranger = User::factory()->create();
        $strangerToken = $stranger->createToken('test-device')->plainTextToken;
        $this->withToken($strangerToken)
            ->get("/api/mobile/lodging-attachments/{$attachment->id}")
            ->assertStatus(403);
    }

    public function test_delete_removes_row_and_file(): void
    {
        Storage::fake(config('filesystems.default'));
        ['band' => $band, 'lodging' => $lodging, 'token' => $token] = $this->createOwnerWithLodging();

        $this->withToken($token)
            ->withHeaders(['X-Band-ID' => $band->id])
            ->post("/api/mobile/bands/{$band->id}/lodgings/{$lodging->id}/attachments", [
                'file' => UploadedFile::fake()->image('gone.jpg'),
            ])->assertStatus(201);

        $attachment = $lodging->attachments()->first();
        $storedPath = $attachment->stored_filename;

        $this->withToken($token)
            ->withHeaders(['X-Band-ID' => $band->id])
            ->deleteJson("/api/mobile/bands/{$band->id}/lodgings/{$lodging->id}/attachments/{$attachment->id}")
            ->assertOk();

        $this->assertDatabaseMissing('lodging_attachments', ['id' => $attachment->id]);
        Storage::disk(config('filesystems.default'))->assertMissing($storedPath);
    }

    public function test_attachment_from_other_lodging_404s(): void
    {
        Storage::fake(config('filesystems.default'));
        ['band' => $band, 'lodging' => $lodging, 'token' => $token] = $this->createOwnerWithLodging();
        $otherLodging = Lodging::factory()->create(['band_id' => $band->id]);
        $foreign = $otherLodging->attachments()->create([
            'filename' => 'x.jpg', 'stored_filename' => 'x/x.jpg',
            'mime_type' => 'image/jpeg', 'file_size' => 1, 'disk' => config('filesystems.default'),
        ]);

        $this->withToken($token)
            ->withHeaders(['X-Band-ID' => $band->id])
            ->deleteJson("/api/mobile/bands/{$band->id}/lodgings/{$lodging->id}/attachments/{$foreign->id}")
            ->assertStatus(404);
    }
}
