<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Bands;
use App\Models\BandOwners;
use App\Models\BandMembers;
use App\Models\MediaFile;
use App\Models\BandStorageQuota;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class MediaLibraryTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $band;

    protected function setUp(): void
    {
        parent::setUp();

        // Fake S3 storage
        Storage::fake('s3');
        config(['filesystems.default' => 's3']);

        // Create test user and band
        $this->user = User::factory()->create();
        $this->band = Bands::factory()->create([
            'site_name' => 'test-band'
        ]);

        // Make user an owner of the band
        BandOwners::create([
            'user_id' => $this->user->id,
            'band_id' => $this->band->id
        ]);
    }

    public function test_displays_media_library_index_page()
    {
        $response = $this->actingAs($this->user)
            ->get(route('media.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('Media/Index')
                ->has('media')
                ->has('tags')
                ->has('quota')
        );
    }

    public function test_uploads_a_file_successfully()
    {
        $file = UploadedFile::fake()->image('test-photo.jpg', 600, 400)->size(100);

        $response = $this->actingAs($this->user)
            ->post(route('media.upload'), [
                'band_id' => $this->band->id,
                'files' => [$file],
                'title' => 'Test Photo',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('successMessage', 'Successfully uploaded 1 file(s)');

        // Assert file was stored
        $this->assertDatabaseHas('media_files', [
            'band_id' => $this->band->id,
            'user_id' => $this->user->id,
            'filename' => 'test-photo.jpg',
            'media_type' => 'image'
        ]);

        // Assert storage quota was updated
        $quota = BandStorageQuota::where('band_id', $this->band->id)->first();
        $this->assertNotNull($quota);
        $this->assertEquals($file->getSize(), $quota->quota_used);
    }

    public function test_uploads_multiple_files()
    {
        $file1 = UploadedFile::fake()->image('photo1.jpg')->size(50);
        $file2 = UploadedFile::fake()->image('photo2.jpg')->size(75);

        $response = $this->actingAs($this->user)
            ->post(route('media.upload'), [
                'band_id' => $this->band->id,
                'files' => [$file1, $file2]
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('successMessage', 'Successfully uploaded 2 file(s)');

        $this->assertCount(2, MediaFile::where('band_id', $this->band->id)->get());
    }

    public function test_enforces_storage_quota()
    {
        // Set quota to 1KB
        BandStorageQuota::create([
            'band_id' => $this->band->id,
            'quota_limit' => 1024,
            'quota_used' => 0
        ]);

        // Try to upload 2KB file
        $file = UploadedFile::fake()->image('large-photo.jpg')->size(2);

        $response = $this->actingAs($this->user)
            ->post(route('media.upload'), [
                'band_id' => $this->band->id,
                'files' => [$file]
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('errorMessage');
        $this->assertStringContainsString('quota exceeded', session('errorMessage'));
    }

    public function test_prevents_upload_without_permission()
    {
        // Create a non-owner user
        $member = User::factory()->create();
        BandMembers::create([
            'user_id' => $member->id,
            'band_id' => $this->band->id
        ]);

        // Set write_media permission to false
        $member->permissionsForBand($this->band->id)->update(['write_media' => false]);

        $file = UploadedFile::fake()->image('test.jpg');

        $response = $this->actingAs($member)
            ->post(route('media.upload'), [
                'band_id' => $this->band->id,
                'files' => [$file]
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('errorMessage', 'Permission denied');
    }

    public function test_deletes_a_media_file()
    {
        $file = UploadedFile::fake()->image('delete-me.jpg')->size(100);

        // Upload file
        $this->actingAs($this->user)
            ->post(route('media.upload'), [
                'band_id' => $this->band->id,
                'files' => [$file]
            ]);

        $mediaFile = MediaFile::first();
        $initialQuota = BandStorageQuota::where('band_id', $this->band->id)->first()->quota_used;

        // Delete file
        $response = $this->actingAs($this->user)
            ->delete(route('media.destroy', $mediaFile->id));

        $response->assertRedirect();
        $response->assertSessionHas('successMessage', 'Media deleted successfully');

        // Assert file was soft deleted
        $this->assertSoftDeleted('media_files', ['id' => $mediaFile->id]);

        // Assert quota was updated
        $updatedQuota = BandStorageQuota::where('band_id', $this->band->id)->first()->quota_used;
        $this->assertEquals(0, $updatedQuota);
    }

    public function test_downloads_a_media_file()
    {
        $file = UploadedFile::fake()->image('download-me.jpg');

        $this->actingAs($this->user)
            ->post(route('media.upload'), [
                'band_id' => $this->band->id,
                'files' => [$file]
            ]);

        $mediaFile = MediaFile::first();

        $response = $this->actingAs($this->user)
            ->get(route('media.download', $mediaFile->id));

        $response->assertStatus(200);
        $response->assertHeader('Content-Disposition', 'attachment; filename="download-me.jpg"');
    }

    public function test_serves_a_media_file_inline()
    {
        $file = UploadedFile::fake()->image('view-me.jpg');

        $this->actingAs($this->user)
            ->post(route('media.upload'), [
                'band_id' => $this->band->id,
                'files' => [$file]
            ]);

        $mediaFile = MediaFile::first();

        $response = $this->actingAs($this->user)
            ->get(route('media.serve', $mediaFile->id));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'image/jpeg');
        $response->assertHeader('Content-Disposition', 'inline; filename="view-me.jpg"');
    }

    public function test_searches_media_by_filename()
    {
        MediaFile::factory()->create([
            'band_id' => $this->band->id,
            'filename' => 'summer-vacation.jpg',
            'title' => 'Summer Vacation'
        ]);

        MediaFile::factory()->create([
            'band_id' => $this->band->id,
            'filename' => 'winter-trip.jpg',
            'title' => 'Winter Trip'
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('media.index', ['search' => 'summer', 'band_id' => $this->band->id]));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->has('media.data', 1)
                ->where('media.data.0.filename', 'summer-vacation.jpg')
        );
    }

    public function test_filters_media_by_type()
    {
        MediaFile::factory()->create([
            'band_id' => $this->band->id,
            'media_type' => 'image'
        ]);

        MediaFile::factory()->create([
            'band_id' => $this->band->id,
            'media_type' => 'document'
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('media.index', ['media_type' => 'image', 'band_id' => $this->band->id]));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->has('media.data', 1)
                ->where('media.data.0.media_type', 'image')
        );
    }

    public function test_creates_a_tag()
    {
        $response = $this->actingAs($this->user)
            ->postJson(route('media.tags.store'), [
                'band_id' => $this->band->id,
                'name' => 'Promo Materials',
                'color' => '#FF5733'
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('media_tags', [
            'band_id' => $this->band->id,
            'name' => 'Promo Materials',
            'color' => '#FF5733'
        ]);
    }

    public function test_updates_media_file_metadata()
    {
        $mediaFile = MediaFile::factory()->create([
            'band_id' => $this->band->id,
            'title' => 'Old Title'
        ]);

        $response = $this->actingAs($this->user)
            ->patch(route('media.update', $mediaFile->id), [
                'title' => 'New Title',
                'description' => 'Updated description'
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('media_files', [
            'id' => $mediaFile->id,
            'title' => 'New Title',
            'description' => 'Updated description'
        ]);
    }

    public function test_prevents_access_to_other_bands_media()
    {
        $otherBand = Bands::factory()->create();
        $mediaFile = MediaFile::factory()->create([
            'band_id' => $otherBand->id
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('media.download', $mediaFile->id));

        $response->assertStatus(403);
    }

    public function test_determines_media_type_correctly()
    {
        $tests = [
            ['file' => UploadedFile::fake()->image('image.jpg'), 'type' => 'image'],
            ['file' => UploadedFile::fake()->create('document.pdf', 100, 'application/pdf'), 'type' => 'document'],
            ['file' => UploadedFile::fake()->create('audio.mp3', 100, 'audio/mpeg'), 'type' => 'audio'],
        ];

        foreach ($tests as $test) {
            $this->actingAs($this->user)
                ->post(route('media.upload'), [
                    'band_id' => $this->band->id,
                    'files' => [$test['file']]
                ]);

            $this->assertDatabaseHas('media_files', [
                'band_id' => $this->band->id,
                'media_type' => $test['type']
            ]);

            // Delete all media files (respects foreign key constraints)
            MediaFile::query()->delete();
        }
    }

    public function test_recalculates_storage_quota()
    {
        $quota = BandStorageQuota::create([
            'band_id' => $this->band->id,
            'quota_used' => 0
        ]);

        // Create some media files
        MediaFile::factory()->create([
            'band_id' => $this->band->id,
            'file_size' => 1000
        ]);

        MediaFile::factory()->create([
            'band_id' => $this->band->id,
            'file_size' => 2000
        ]);

        $quota->recalculate();

        $this->assertEquals(3000, $quota->fresh()->quota_used);
    }
}
