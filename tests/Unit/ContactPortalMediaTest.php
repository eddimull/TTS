<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Bands;
use App\Models\Events;
use App\Models\Bookings;
use App\Models\Contacts;
use App\Models\MediaFile;
use App\Services\MediaLibraryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

class ContactPortalMediaTest extends TestCase
{
    use RefreshDatabase;

    public function test_contact_can_access_media_from_their_bookings(): void
    {
        $booking = Bookings::factory()->create([
            'enable_portal_media_access' => true,
            'date' => '2024-06-15',
            'name' => 'Wedding Event',
        ]);

        $contact = Contacts::factory()->create([
            'band_id' => $booking->band_id,
            'can_login' => true,
        ]);

        // Associate contact with booking
        $booking->contacts()->attach($contact->id);

        // Create event with folder
        $event = Events::create([
            'eventable_type' => 'App\Models\Bookings',
            'eventable_id' => $booking->id,
            'event_type_id' => $booking->event_type_id,
            'date' => $booking->date,
            'title' => $booking->name,
            'media_folder_path' => '2024/06/wedding-event',
            'enable_portal_media_access' => true,
            'key' => (string) Str::uuid(),
        ]);

        // Create media in event folder
        $media = MediaFile::factory()->create([
            'band_id' => $booking->band_id,
            'folder_path' => '2024/06/wedding-event',
        ]);

        $mediaService = app(MediaLibraryService::class);
        $accessibleMedia = $mediaService->getContactAccessibleMedia($contact);

        $this->assertCount(1, $accessibleMedia);
        $this->assertTrue($accessibleMedia->contains($media));
    }

    public function test_contact_cannot_access_media_from_other_bookings(): void
    {
        $booking1 = Bookings::factory()->create([
            'enable_portal_media_access' => true,
        ]);

        $booking2 = Bookings::factory()->create([
            'band_id' => $booking1->band_id,
            'enable_portal_media_access' => true,
        ]);

        $contact = Contacts::factory()->create([
            'band_id' => $booking1->band_id,
            'can_login' => true,
        ]);

        // Contact only associated with booking1
        $booking1->contacts()->attach($contact->id);

        // Create event for booking2
        $event2 = Events::create([
            'eventable_type' => 'App\Models\Bookings',
            'eventable_id' => $booking2->id,
            'event_type_id' => $booking2->event_type_id,
            'date' => $booking2->date,
            'title' => $booking2->name,
            'media_folder_path' => '2024/07/other-event',
            'enable_portal_media_access' => true,
            'key' => (string) Str::uuid(),
        ]);

        // Create media for booking2
        $media2 = MediaFile::factory()->create([
            'band_id' => $booking1->band_id,
            'folder_path' => '2024/07/other-event',
        ]);

        $mediaService = app(MediaLibraryService::class);
        $accessibleMedia = $mediaService->getContactAccessibleMedia($contact);

        // Should not contain media from booking2
        $this->assertFalse($accessibleMedia->contains($media2));
    }

    public function test_contact_cannot_access_media_when_portal_access_disabled(): void
    {
        $booking = Bookings::factory()->create([
            'enable_portal_media_access' => true,
        ]);

        $contact = Contacts::factory()->create([
            'band_id' => $booking->band_id,
            'can_login' => true,
        ]);

        $booking->contacts()->attach($contact->id);

        // Create event with portal access DISABLED
        $event = Events::create([
            'eventable_type' => 'App\Models\Bookings',
            'eventable_id' => $booking->id,
            'event_type_id' => $booking->event_type_id,
            'date' => $booking->date,
            'title' => $booking->name,
            'media_folder_path' => '2024/06/private-event',
            'enable_portal_media_access' => false, // Disabled
            'key' => (string) Str::uuid(),
        ]);

        $media = MediaFile::factory()->create([
            'band_id' => $booking->band_id,
            'folder_path' => '2024/06/private-event',
        ]);

        $mediaService = app(MediaLibraryService::class);
        $accessibleMedia = $mediaService->getContactAccessibleMedia($contact);

        // Should not have access to media from disabled event
        $this->assertCount(0, $accessibleMedia);
    }

    public function test_contact_can_access_multiple_events_from_same_booking(): void
    {
        $booking = Bookings::factory()->create([
            'enable_portal_media_access' => true,
        ]);

        $contact = Contacts::factory()->create([
            'band_id' => $booking->band_id,
            'can_login' => true,
        ]);

        $booking->contacts()->attach($contact->id);

        // Create two events for same booking
        $event1 = Events::create([
            'eventable_type' => 'App\Models\Bookings',
            'eventable_id' => $booking->id,
            'event_type_id' => $booking->event_type_id,
            'date' => '2024-06-15',
            'title' => 'Ceremony',
            'media_folder_path' => '2024/06/ceremony',
            'enable_portal_media_access' => true,
            'key' => (string) Str::uuid(),
        ]);

        $event2 = Events::create([
            'eventable_type' => 'App\Models\Bookings',
            'eventable_id' => $booking->id,
            'event_type_id' => $booking->event_type_id,
            'date' => '2024-06-15',
            'title' => 'Reception',
            'media_folder_path' => '2024/06/reception',
            'enable_portal_media_access' => true,
            'key' => (string) Str::uuid(),
        ]);

        // Create media for both events
        $media1 = MediaFile::factory()->create([
            'band_id' => $booking->band_id,
            'folder_path' => '2024/06/ceremony',
        ]);

        $media2 = MediaFile::factory()->create([
            'band_id' => $booking->band_id,
            'folder_path' => '2024/06/reception',
        ]);

        $mediaService = app(MediaLibraryService::class);
        $accessibleMedia = $mediaService->getContactAccessibleMedia($contact);

        // Should have access to media from both events
        $this->assertCount(2, $accessibleMedia);
        $this->assertTrue($accessibleMedia->contains($media1));
        $this->assertTrue($accessibleMedia->contains($media2));
    }

    public function test_contact_with_no_bookings_gets_empty_media_collection(): void
    {
        $band = Bands::factory()->create();
        $contact = Contacts::factory()->create([
            'band_id' => $band->id,
            'can_login' => true,
        ]);

        $mediaService = app(MediaLibraryService::class);
        $accessibleMedia = $mediaService->getContactAccessibleMedia($contact);

        $this->assertCount(0, $accessibleMedia);
        $this->assertTrue($accessibleMedia->isEmpty());
    }
}
