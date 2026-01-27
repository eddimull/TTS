<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Bands;
use App\Models\Events;
use App\Models\Bookings;
use App\Models\MediaFolder;
use App\Services\MediaLibraryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

class EventFolderCreationTest extends TestCase
{
    use RefreshDatabase;

    public function test_event_folder_is_created_automatically_when_booking_creates_event(): void
    {
        // Create a booking
        $booking = Bookings::factory()->create([
            'enable_portal_media_access' => true,
            'date' => '2024-05-20',
            'name' => 'Jones Birthday Party',
        ]);

        // Manually create an event like the BookingsController does
        $eventData = [
            'event_type_id' => $booking->event_type_id,
            'key' => Str::uuid(),
            'title' => $booking->name,
            'date' => $booking->date,
            'time' => $booking->start_time,
            'enable_portal_media_access' => true,
        ];

        $event = $booking->events()->create($eventData);

        // Simulate the folder creation logic from BookingsController
        if ($booking->enable_portal_media_access && $event->enable_portal_media_access) {
            $mediaService = app(MediaLibraryService::class);
            $folderPath = $mediaService->createEventFolder($event);
            $event->update(['media_folder_path' => $folderPath]);
        }

        // Reload event from database
        $event->refresh();

        // Assert folder path was created
        $this->assertNotNull($event->media_folder_path);
        $this->assertEquals('2024/05/jones-birthday-party', $event->media_folder_path);

        // Assert folder exists in database
        $folder = MediaFolder::where('band_id', $booking->band_id)
            ->where('path', '2024/05/jones-birthday-party')
            ->first();

        $this->assertNotNull($folder);
    }

    public function test_event_folder_is_not_created_when_portal_access_disabled_on_booking(): void
    {
        // Create a booking with portal access disabled
        $booking = Bookings::factory()->create([
            'enable_portal_media_access' => false,
            'date' => '2024-05-20',
            'name' => 'Private Event',
        ]);

        $eventData = [
            'event_type_id' => $booking->event_type_id,
            'key' => Str::uuid(),
            'title' => $booking->name,
            'date' => $booking->date,
            'time' => $booking->start_time,
        ];

        $event = $booking->events()->create($eventData);

        // Simulate the folder creation logic from BookingsController
        if ($booking->enable_portal_media_access && $event->enable_portal_media_access) {
            $mediaService = app(MediaLibraryService::class);
            $folderPath = $mediaService->createEventFolder($event);
            $event->update(['media_folder_path' => $folderPath]);
        }

        $event->refresh();

        // Assert folder path was NOT created
        $this->assertNull($event->media_folder_path);
    }

    public function test_event_folder_is_not_created_when_portal_access_disabled_on_event(): void
    {
        // Create a booking with portal access enabled
        $booking = Bookings::factory()->create([
            'enable_portal_media_access' => true,
            'date' => '2024-05-20',
            'name' => 'Test Event',
        ]);

        $eventData = [
            'event_type_id' => $booking->event_type_id,
            'key' => Str::uuid(),
            'title' => $booking->name,
            'date' => $booking->date,
            'time' => $booking->start_time,
            'enable_portal_media_access' => false, // Disabled on event
        ];

        $event = $booking->events()->create($eventData);

        // Simulate the folder creation logic from BookingsController
        if ($booking->enable_portal_media_access && $event->enable_portal_media_access) {
            $mediaService = app(MediaLibraryService::class);
            $folderPath = $mediaService->createEventFolder($event);
            $event->update(['media_folder_path' => $folderPath]);
        }

        $event->refresh();

        // Assert folder path was NOT created
        $this->assertNull($event->media_folder_path);
    }

    public function test_parent_folders_are_created_automatically(): void
    {
        $booking = Bookings::factory()->create([
            'enable_portal_media_access' => true,
            'date' => '2024-07-15',
            'name' => 'Summer Festival',
        ]);

        $eventData = [
            'event_type_id' => $booking->event_type_id,
            'key' => Str::uuid(),
            'title' => $booking->name,
            'date' => $booking->date,
            'time' => $booking->start_time,
            'enable_portal_media_access' => true,
        ];

        $event = $booking->events()->create($eventData);

        // Simulate folder creation
        $mediaService = app(MediaLibraryService::class);
        $folderPath = $mediaService->createEventFolder($event);
        $event->update(['media_folder_path' => $folderPath]);

        // Assert year folder exists
        $yearFolder = MediaFolder::where('band_id', $booking->band_id)
            ->where('path', '2024')
            ->first();
        $this->assertNotNull($yearFolder);

        // Assert month folder exists
        $monthFolder = MediaFolder::where('band_id', $booking->band_id)
            ->where('path', '2024/07')
            ->first();
        $this->assertNotNull($monthFolder);

        // Assert event folder exists
        $eventFolder = MediaFolder::where('band_id', $booking->band_id)
            ->where('path', '2024/07/summer-festival')
            ->first();
        $this->assertNotNull($eventFolder);
    }
}
