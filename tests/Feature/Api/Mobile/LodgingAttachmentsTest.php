<?php
namespace Tests\Feature\Api\Mobile;

use App\Models\Bands;
use App\Models\Lodging;
use App\Models\User;
use App\Events\BandDataChanged;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
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

    /**
     * The attachment lives on a child table (lodging_attachments), so the
     * lodging row's own tracked columns don't change on upload — only
     * touch()'s updated_at. BroadcastsBandChanges::broadcastHasMeaningfulChanges()
     * ignores updated_at-only diffs, so without Lodging::broadcastRefresh()
     * bypassing that gate, mobile clients would get no realtime signal at
     * all on attachment upload.
     */
    public function test_upload_broadcasts_lodging_updated(): void
    {
        Storage::fake(config('filesystems.default'));
        Event::fake([BandDataChanged::class]);
        ['band' => $band, 'lodging' => $lodging, 'token' => $token] = $this->createOwnerWithLodging();

        $this->withToken($token)
            ->withHeaders(['X-Band-ID' => $band->id])
            ->post("/api/mobile/bands/{$band->id}/lodgings/{$lodging->id}/attachments", [
                'file' => UploadedFile::fake()->image('confirmation.jpg'),
            ])
            ->assertStatus(201);

        Event::assertDispatched(
            BandDataChanged::class,
            fn (BandDataChanged $e) => $e->bandId === $band->id
                && $e->model === 'lodging'
                && $e->id === $lodging->id
                && $e->action === 'updated',
        );
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

    /**
     * I1 regression: LodgingAttachment::lodging() is a plain belongsTo, so
     * SoftDeletingScope excludes a soft-deleted parent Lodging by default —
     * `$attachment->lodging` resolves to null and dereferencing ->band_id on
     * it was a fatal error. Decision: attachments of a soft-deleted stay
     * 404 — the stay is gone, so its attachments are gone too.
     */
    public function test_serve_404s_for_attachment_of_a_soft_deleted_lodging(): void
    {
        Storage::fake(config('filesystems.default'));
        ['lodging' => $lodging, 'token' => $token] = $this->createOwnerWithLodging();
        $attachment = $lodging->attachments()->create([
            'filename' => 'gone.jpg', 'stored_filename' => 'x/gone.jpg',
            'mime_type' => 'image/jpeg', 'file_size' => 1, 'disk' => config('filesystems.default'),
        ]);
        Storage::disk($attachment->disk)->put($attachment->stored_filename, 'bytes');

        $lodging->delete(); // soft delete

        $this->withToken($token)
            ->getJson("/api/mobile/lodging-attachments/{$attachment->id}")
            ->assertStatus(404);
    }

    /**
     * I2: non-image/PDF mimes must be forced to download (not rendered
     * inline) to avoid a browser executing/rendering an arbitrary uploaded
     * file type. Images/PDF remain inline.
     */
    public function test_serve_forces_download_disposition_for_non_previewable_mime(): void
    {
        Storage::fake(config('filesystems.default'));
        ['band' => $band, 'lodging' => $lodging, 'token' => $token] = $this->createOwnerWithLodging();
        $attachment = $lodging->attachments()->create([
            'filename' => 'itinerary.html', 'stored_filename' => 'x/itinerary.html',
            'mime_type' => 'text/html', 'file_size' => 1, 'disk' => config('filesystems.default'),
        ]);
        Storage::disk($attachment->disk)->put($attachment->stored_filename, '<script>alert(1)</script>');

        $response = $this->withToken($token)
            ->getJson("/api/mobile/lodging-attachments/{$attachment->id}")
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        $this->assertStringStartsWith('attachment', $response->headers->get('Content-Disposition'));
    }

    public function test_serve_keeps_inline_disposition_for_images(): void
    {
        Storage::fake(config('filesystems.default'));
        ['band' => $band, 'lodging' => $lodging, 'token' => $token] = $this->createOwnerWithLodging();
        $attachment = $lodging->attachments()->create([
            'filename' => 'photo.jpg', 'stored_filename' => 'x/photo.jpg',
            'mime_type' => 'image/jpeg', 'file_size' => 1, 'disk' => config('filesystems.default'),
        ]);
        Storage::disk($attachment->disk)->put($attachment->stored_filename, 'bytes');

        $response = $this->withToken($token)
            ->getJson("/api/mobile/lodging-attachments/{$attachment->id}")
            ->assertOk();

        $this->assertStringStartsWith('inline', $response->headers->get('Content-Disposition'));
    }

    /**
     * A filename containing a double quote and CRLF is user-controlled
     * (uploader's original filename) — naively concatenating it into
     * `filename="..."` would let it break out of the quoted-string and
     * inject arbitrary header bytes. HeaderUtils::makeDisposition() must
     * produce a single well-formed header value with no raw quote/newline.
     */
    public function test_serve_sanitizes_malicious_filename_in_content_disposition(): void
    {
        Storage::fake(config('filesystems.default'));
        ['band' => $band, 'lodging' => $lodging, 'token' => $token] = $this->createOwnerWithLodging();
        $maliciousName = "evil\".jpg\r\nX-Injected: 1";
        $attachment = $lodging->attachments()->create([
            'filename' => $maliciousName, 'stored_filename' => 'x/evil.jpg',
            'mime_type' => 'image/jpeg', 'file_size' => 1, 'disk' => config('filesystems.default'),
        ]);
        Storage::disk($attachment->disk)->put($attachment->stored_filename, 'bytes');

        $response = $this->withToken($token)
            ->getJson("/api/mobile/lodging-attachments/{$attachment->id}")
            ->assertOk();

        $header = $response->headers->get('Content-Disposition');
        $this->assertStringStartsWith('inline', $header);
        // No raw CR/LF bytes anywhere in the header value — the CRLF in the
        // malicious filename must come through only percent-encoded (inside
        // the RFC 5987 filename*= token), never as literal header-breaking
        // bytes. This is the actual injection vector: a raw \r\n would let
        // the attacker start a new header line.
        $this->assertStringNotContainsString("\r", $header);
        $this->assertStringNotContainsString("\n", $header);
        // The quoted-string filename="" token (ASCII fallback) must not
        // contain a raw, unescaped double quote — it would prematurely close
        // the token and let trailing text be interpreted as new parameters.
        $this->assertMatchesRegularExpression('/filename="(?:[^"\\\\]|\\\\.)*"/', $header);
        // A single, well-formed header value overall (this is what
        // response()->headers->get() would already collapse/reject if the
        // underlying header bag received multiple lines).
        $this->assertIsString($header);
    }
}
