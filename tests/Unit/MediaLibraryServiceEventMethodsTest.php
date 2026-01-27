<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Events;
use App\Models\Bookings;
use App\Models\Contacts;
use App\Models\MediaFile;
use App\Models\MediaFolder;
use App\Services\MediaLibraryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

class MediaLibraryServiceEventMethodsTest extends TestCase
{
    use RefreshDatabase;

    protected MediaLibraryService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new MediaLibraryService();
    }

    public function test_create_event_folder_generates_correct_path(): void
    {
        $booking = Bookings::factory()->create();

        $event = Events::create([
            'eventable_type' => 'App\Models\Bookings',
            'eventable_id' => $booking->id,
            'event_type_id' => $booking->event_type_id,
            'date' => '2024-03-15',
            'title' => 'Smith Wedding',
            'key' => (string) Str::uuid(),
        ]);

        $folderPath = $this->service->createEventFolder($event);

        $this->assertEquals('2024/03/smith-wedding', $folderPath);

        // Verify folder was created in database
        $folder = MediaFolder::where('band_id', $booking->band_id)
            ->where('path', '2024/03/smith-wedding')
            ->first();

        $this->assertNotNull($folder);
    }

    public function test_create_event_folder_handles_duplicate_names(): void
    {
        $booking = Bookings::factory()->create();

        // Create first event
        $event1 = Events::create([
            'eventable_type' => 'App\Models\Bookings',
            'eventable_id' => $booking->id,
            'event_type_id' => $booking->event_type_id,
            'date' => '2024-03-15',
            'title' => 'Wedding',
            'key' => (string) Str::uuid(),
        ]);

        $folderPath1 = $this->service->createEventFolder($event1);

        // Create second event with same name and date
        $event2 = Events::create([
            'eventable_type' => 'App\Models\Bookings',
            'eventable_id' => $booking->id,
            'event_type_id' => $booking->event_type_id,
            'date' => '2024-03-15',
            'title' => 'Wedding',
            'key' => (string) Str::uuid(),
        ]);

        $folderPath2 = $this->service->createEventFolder($event2);

        $this->assertEquals('2024/03/wedding', $folderPath1);
        $this->assertEquals('2024/03/wedding-1', $folderPath2);
        $this->assertNotEquals($folderPath1, $folderPath2);
    }

    public function test_create_event_folder_creates_parent_folders(): void
    {
        $booking = Bookings::factory()->create();

        $event = Events::create([
            'eventable_type' => 'App\Models\Bookings',
            'eventable_id' => $booking->id,
            'event_type_id' => $booking->event_type_id,
            'date' => '2024-03-15',
            'title' => 'Test Event',
            'key' => (string) Str::uuid(),
        ]);

        $this->service->createEventFolder($event);

        // Verify year folder exists
        $yearFolder = MediaFolder::where('band_id', $booking->band_id)
            ->where('path', '2024')
            ->first();
        $this->assertNotNull($yearFolder);

        // Verify month folder exists
        $monthFolder = MediaFolder::where('band_id', $booking->band_id)
            ->where('path', '2024/03')
            ->first();
        $this->assertNotNull($monthFolder);

        // Verify event folder exists
        $eventFolder = MediaFolder::where('band_id', $booking->band_id)
            ->where('path', '2024/03/test-event')
            ->first();
        $this->assertNotNull($eventFolder);
    }

    public function test_get_event_media_returns_media_by_folder_path(): void
    {
        $booking = Bookings::factory()->create();

        $event = Events::create([
            'eventable_type' => 'App\Models\Bookings',
            'eventable_id' => $booking->id,
            'event_type_id' => $booking->event_type_id,
            'date' => '2024-03-15',
            'title' => 'Test Event',
            'media_folder_path' => '2024/03/test-event',
            'key' => (string) Str::uuid(),
        ]);

        // Create media files in the event folder
        $media1 = MediaFile::factory()->create([
            'band_id' => $booking->band_id,
            'folder_path' => '2024/03/test-event',
        ]);

        $media2 = MediaFile::factory()->create([
            'band_id' => $booking->band_id,
            'folder_path' => '2024/03/test-event',
        ]);

        // Create media in different folder (should not be returned)
        MediaFile::factory()->create([
            'band_id' => $booking->band_id,
            'folder_path' => '2024/03/other-event',
        ]);

        $eventMedia = $this->service->getEventMedia($event);

        $this->assertCount(2, $eventMedia);
        $this->assertTrue($eventMedia->contains($media1));
        $this->assertTrue($eventMedia->contains($media2));
    }

    public function test_get_contact_accessible_media_returns_booking_media(): void
    {
        $booking = Bookings::factory()->create([
            'enable_portal_media_access' => true,
        ]);

        $contact = Contacts::factory()->create([
            'band_id' => $booking->band_id,
        ]);

        // Associate contact with booking
        $booking->contacts()->attach($contact->id);

        // Create event for booking
        $event = Events::create([
            'eventable_type' => 'App\Models\Bookings',
            'eventable_id' => $booking->id,
            'event_type_id' => $booking->event_type_id,
            'date' => '2024-03-15',
            'title' => 'Test Event',
            'media_folder_path' => '2024/03/test-event',
            'enable_portal_media_access' => true,
            'key' => (string) Str::uuid(),
        ]);

        // Create media in event folder
        $media1 = MediaFile::factory()->create([
            'band_id' => $booking->band_id,
            'folder_path' => '2024/03/test-event',
        ]);

        // Create media in different booking (should not be returned)
        $otherBooking = Bookings::factory()->create([
            'band_id' => $booking->band_id,
        ]);
        $otherEvent = Events::create([
            'eventable_type' => 'App\Models\Bookings',
            'eventable_id' => $otherBooking->id,
            'event_type_id' => $otherBooking->event_type_id,
            'date' => '2024-04-20',
            'title' => 'Other Event',
            'media_folder_path' => '2024/04/other-event',
            'enable_portal_media_access' => true,
            'key' => (string) Str::uuid(),
        ]);
        MediaFile::factory()->create([
            'band_id' => $booking->band_id,
            'folder_path' => '2024/04/other-event',
        ]);

        $accessibleMedia = $this->service->getContactAccessibleMedia($contact);

        $this->assertCount(1, $accessibleMedia);
        $this->assertTrue($accessibleMedia->contains($media1));
    }

    public function test_get_contact_accessible_media_respects_portal_access_flag(): void
    {
        $booking = Bookings::factory()->create([
            'enable_portal_media_access' => true,
        ]);

        $contact = Contacts::factory()->create([
            'band_id' => $booking->band_id,
        ]);

        $booking->contacts()->attach($contact->id);

        // Create event with portal access disabled
        $event = Events::create([
            'eventable_type' => 'App\Models\Bookings',
            'eventable_id' => $booking->id,
            'event_type_id' => $booking->event_type_id,
            'date' => '2024-03-15',
            'title' => 'Private Event',
            'media_folder_path' => '2024/03/private-event',
            'enable_portal_media_access' => false,
            'key' => (string) Str::uuid(),
        ]);

        MediaFile::factory()->create([
            'band_id' => $booking->band_id,
            'folder_path' => '2024/03/private-event',
        ]);

        $accessibleMedia = $this->service->getContactAccessibleMedia($contact);

        // Should return empty because enable_portal_media_access is false
        $this->assertCount(0, $accessibleMedia);
    }
}
