<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Bands;
use App\Models\BandOwners;
use App\Models\BandMembers;
use App\Models\MediaFile;
use App\Models\BandStorageQuota;
use App\Models\userPermissions;
use App\Services\MediaLibraryService;
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

        // Create user permissions for media access
        // Note: Band owners should have all permissions automatically,
        // but we create this explicitly to ensure tests work reliably
        userPermissions::create([
            'user_id' => $this->user->id,
            'band_id' => $this->band->id,
            'read_media' => true,
            'write_media' => true
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

        // Create permissions with write_media set to false
        userPermissions::create([
            'user_id' => $member->id,
            'band_id' => $this->band->id,
            'read_media' => true,
            'write_media' => false
        ]);

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

        $response->assertRedirect();
        $response->assertSessionHas('errorMessage', 'Permission denied');
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

    // ========================================
    // Folder Structure Tests
    // ========================================

    public function test_gets_root_level_folders()
    {
        // Create files in various folders
        MediaFile::factory()->create([
            'band_id' => $this->band->id,
            'folder_path' => 'Photos',
        ]);
        MediaFile::factory()->create([
            'band_id' => $this->band->id,
            'folder_path' => 'Documents',
        ]);
        MediaFile::factory()->create([
            'band_id' => $this->band->id,
            'folder_path' => 'Drive',
        ]);
        MediaFile::factory()->create([
            'band_id' => $this->band->id,
            'folder_path' => 'Drive/Subfolder',
        ]);

        $service = app(MediaLibraryService::class);
        $subfolders = $service->getSubfoldersOf($this->band->id, null);

        // Should return 3 root folders: Photos, Documents, Drive
        $this->assertCount(3, $subfolders);
        $folderNames = array_column($subfolders, 'name');
        $this->assertContains('Photos', $folderNames);
        $this->assertContains('Documents', $folderNames);
        $this->assertContains('Drive', $folderNames);
    }

    public function test_gets_root_level_folders_from_nested_paths()
    {
        // Create files with deep nested paths
        MediaFile::factory()->create([
            'band_id' => $this->band->id,
            'folder_path' => 'Photos/2024/Summer',
        ]);
        MediaFile::factory()->create([
            'band_id' => $this->band->id,
            'folder_path' => 'Photos/2023/Winter',
        ]);
        MediaFile::factory()->create([
            'band_id' => $this->band->id,
            'folder_path' => 'Documents/Work/Projects',
        ]);

        $service = app(MediaLibraryService::class);
        $subfolders = $service->getSubfoldersOf($this->band->id, null);

        // Should extract only top-level: Photos, Documents
        $this->assertCount(2, $subfolders);
        $folderNames = array_column($subfolders, 'name');
        $this->assertContains('Photos', $folderNames);
        $this->assertContains('Documents', $folderNames);
    }

    public function test_gets_immediate_subfolders_of_specific_folder()
    {
        // Create nested folder structure
        MediaFile::factory()->create([
            'band_id' => $this->band->id,
            'folder_path' => 'Drive/Photos',
        ]);
        MediaFile::factory()->create([
            'band_id' => $this->band->id,
            'folder_path' => 'Drive/Documents',
        ]);
        MediaFile::factory()->create([
            'band_id' => $this->band->id,
            'folder_path' => 'Drive/Photos/2024',
        ]);
        MediaFile::factory()->create([
            'band_id' => $this->band->id,
            'folder_path' => 'Drive/Photos/2023',
        ]);

        $service = app(MediaLibraryService::class);
        $subfolders = $service->getSubfoldersOf($this->band->id, 'Drive');

        // Should return only immediate children: Photos, Documents
        $this->assertCount(2, $subfolders);
        $folderPaths = array_column($subfolders, 'path');
        $this->assertContains('Drive/Photos', $folderPaths);
        $this->assertContains('Drive/Documents', $folderPaths);
    }

    public function test_gets_subfolders_at_deeper_level()
    {
        // Create deep nested structure
        MediaFile::factory()->create([
            'band_id' => $this->band->id,
            'folder_path' => 'Drive/Photos/2024/Summer',
        ]);
        MediaFile::factory()->create([
            'band_id' => $this->band->id,
            'folder_path' => 'Drive/Photos/2024/Winter',
        ]);
        MediaFile::factory()->create([
            'band_id' => $this->band->id,
            'folder_path' => 'Drive/Photos/2023',
        ]);

        $service = app(MediaLibraryService::class);
        $subfolders = $service->getSubfoldersOf($this->band->id, 'Drive/Photos');

        // Should return: 2024, 2023
        $this->assertCount(2, $subfolders);
        $folderPaths = array_column($subfolders, 'path');
        $this->assertContains('Drive/Photos/2024', $folderPaths);
        $this->assertContains('Drive/Photos/2023', $folderPaths);
    }

    public function test_handles_empty_folder_path()
    {
        MediaFile::factory()->create([
            'band_id' => $this->band->id,
            'folder_path' => 'Photos',
        ]);

        $service = app(MediaLibraryService::class);

        // Both null and empty string should work the same
        $subfolders1 = $service->getSubfoldersOf($this->band->id, null);
        $subfolders2 = $service->getSubfoldersOf($this->band->id, '');

        $this->assertEquals($subfolders1, $subfolders2);
    }

    public function test_handles_trailing_slash_in_parent_path()
    {
        MediaFile::factory()->create([
            'band_id' => $this->band->id,
            'folder_path' => 'Drive/Photos',
        ]);

        $service = app(MediaLibraryService::class);

        // Both with and without trailing slash should work
        $subfolders1 = $service->getSubfoldersOf($this->band->id, 'Drive');
        $subfolders2 = $service->getSubfoldersOf($this->band->id, 'Drive/');

        $this->assertEquals($subfolders1, $subfolders2);
    }

    public function test_returns_empty_array_when_no_subfolders_exist()
    {
        MediaFile::factory()->create([
            'band_id' => $this->band->id,
            'folder_path' => 'Photos',
        ]);

        $service = app(MediaLibraryService::class);

        // Looking for subfolders of "Documents" which doesn't exist
        $subfolders = $service->getSubfoldersOf($this->band->id, 'Documents');

        $this->assertEmpty($subfolders);
    }

    public function test_counts_files_in_subfolders_recursively()
    {
        // Create Drive/Photos with files at multiple levels
        MediaFile::factory()->create([
            'band_id' => $this->band->id,
            'folder_path' => 'Drive/Photos',
        ]);
        MediaFile::factory()->create([
            'band_id' => $this->band->id,
            'folder_path' => 'Drive/Photos',
        ]);
        MediaFile::factory()->create([
            'band_id' => $this->band->id,
            'folder_path' => 'Drive/Photos/2024',
        ]);

        $service = app(MediaLibraryService::class);
        $subfolders = $service->getSubfoldersOf($this->band->id, 'Drive');

        // Should count all files in Drive/Photos and its subfolders
        $photosFolder = collect($subfolders)->firstWhere('name', 'Photos');
        $this->assertNotNull($photosFolder);
        $this->assertEquals(3, $photosFolder['file_count']); // All files under Drive/Photos*
    }

    public function test_does_not_return_files_from_other_bands()
    {
        $otherBand = Bands::factory()->create();

        MediaFile::factory()->create([
            'band_id' => $this->band->id,
            'folder_path' => 'MyFolder',
        ]);

        MediaFile::factory()->create([
            'band_id' => $otherBand->id,
            'folder_path' => 'OtherFolder',
        ]);

        $service = app(MediaLibraryService::class);
        $subfolders = $service->getSubfoldersOf($this->band->id, null);

        $this->assertCount(1, $subfolders);
        $this->assertEquals('MyFolder', $subfolders[0]['name']);
    }

    public function test_sorts_subfolders_alphabetically()
    {
        MediaFile::factory()->create([
            'band_id' => $this->band->id,
            'folder_path' => 'Zebra',
        ]);
        MediaFile::factory()->create([
            'band_id' => $this->band->id,
            'folder_path' => 'Apple',
        ]);
        MediaFile::factory()->create([
            'band_id' => $this->band->id,
            'folder_path' => 'Mango',
        ]);

        $service = app(MediaLibraryService::class);
        $subfolders = $service->getSubfoldersOf($this->band->id, null);

        $folderNames = array_column($subfolders, 'name');
        $this->assertEquals(['Apple', 'Mango', 'Zebra'], $folderNames);
    }

    public function test_handles_google_drive_nested_structure()
    {
        // Simulate Google Drive sync creating nested folders
        MediaFile::factory()->create([
            'band_id' => $this->band->id,
            'folder_path' => 'Drive',
            'source' => 'google_drive',
        ]);
        MediaFile::factory()->create([
            'band_id' => $this->band->id,
            'folder_path' => 'Drive/Photos',
            'source' => 'google_drive',
        ]);
        MediaFile::factory()->create([
            'band_id' => $this->band->id,
            'folder_path' => 'Drive/Photos/2024',
            'source' => 'google_drive',
        ]);
        MediaFile::factory()->create([
            'band_id' => $this->band->id,
            'folder_path' => 'Drive/Documents',
            'source' => 'google_drive',
        ]);

        $service = app(MediaLibraryService::class);

        // Root level should show "Drive"
        $rootFolders = $service->getSubfoldersOf($this->band->id, null);
        $this->assertCount(1, $rootFolders);
        $this->assertEquals('Drive', $rootFolders[0]['name']);

        // Inside Drive should show "Photos" and "Documents"
        $driveFolders = $service->getSubfoldersOf($this->band->id, 'Drive');
        $this->assertCount(2, $driveFolders);
        $folderNames = array_column($driveFolders, 'name');
        $this->assertContains('Photos', $folderNames);
        $this->assertContains('Documents', $folderNames);

        // Inside Drive/Photos should show "2024"
        $photosFolders = $service->getSubfoldersOf($this->band->id, 'Drive/Photos');
        $this->assertCount(1, $photosFolders);
        $this->assertEquals('2024', $photosFolders[0]['name']);
    }

    public function test_handles_folder_with_spaces_and_numbers()
    {
        // Reproduce the exact issue: Live test folder > Whatever > 53y53y > 44444
        // Add files at both the parent level AND the subfolder level
        MediaFile::factory()->create([
            'band_id' => $this->band->id,
            'folder_path' => 'Live test folder/Whatever/53y53y',
            'filename' => 'file-in-parent.jpg',
        ]);

        MediaFile::factory()->create([
            'band_id' => $this->band->id,
            'folder_path' => 'Live test folder/Whatever/53y53y/44444',
            'filename' => 'file-in-subfolder.jpg',
        ]);

        $service = app(MediaLibraryService::class);

        // When viewing "Live test folder/Whatever/53y53y", should see "44444"
        $subfolders = $service->getSubfoldersOf($this->band->id, 'Live test folder/Whatever/53y53y');

        dump('Subfolders found:', $subfolders);
        dump('All folder paths in DB:', MediaFile::where('band_id', $this->band->id)->pluck('folder_path')->toArray());

        $this->assertCount(1, $subfolders, 'Should find the "44444" subfolder');
        $this->assertEquals('44444', $subfolders[0]['name']);
        $this->assertEquals('Live test folder/Whatever/53y53y/44444', $subfolders[0]['path']);
    }
}
