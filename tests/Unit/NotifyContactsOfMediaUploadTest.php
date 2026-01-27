<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Bands;
use App\Models\Events;
use App\Models\Bookings;
use App\Models\Contacts;
use App\Models\MediaFile;
use App\Jobs\NotifyContactsOfMediaUpload;
use App\Notifications\MediaUploadedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

class NotifyContactsOfMediaUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_notification_job_is_queued_when_uploading_to_event_folder(): void
    {
        Queue::fake();

        $band = Bands::factory()->withOwners()->create();
        $booking = Bookings::factory()->create([
            'band_id' => $band->id,
            'enable_portal_media_access' => true,
        ]);

        $event = Events::create([
            'eventable_type' => 'App\\Models\\Bookings',
            'eventable_id' => $booking->id,
            'event_type_id' => $booking->event_type_id,
            'date' => $booking->date,
            'title' => $booking->name,
            'media_folder_path' => '2024/06/test-event',
            'enable_portal_media_access' => true,
            'key' => (string) Str::uuid(),
        ]);

        $mediaService = app(\App\Services\MediaLibraryService::class);
        $mediaService->queueEventMediaNotification($event->id);

        Queue::assertPushed(NotifyContactsOfMediaUpload::class, function ($job) use ($event) {
            return $job->eventId === $event->id;
        });
    }

    public function test_multiple_uploads_update_cache_timestamp(): void
    {
        Queue::fake();

        $eventId = 1;
        $cacheKey = "event_media_upload_notification:{$eventId}";

        $mediaService = app(\App\Services\MediaLibraryService::class);

        // First upload
        $mediaService->queueEventMediaNotification($eventId);
        $firstTimestamp = Cache::get($cacheKey);
        $this->assertNotNull($firstTimestamp, 'First timestamp should be set');

        // Wait a moment
        sleep(1);

        // Second upload
        $mediaService->queueEventMediaNotification($eventId);
        $secondTimestamp = Cache::get($cacheKey);
        $this->assertNotNull($secondTimestamp, 'Second timestamp should be set');

        // Second timestamp should be greater than first
        $this->assertGreaterThan($firstTimestamp, $secondTimestamp, 'Second timestamp should be newer');

        // Both jobs should have been queued
        Queue::assertPushed(NotifyContactsOfMediaUpload::class, 2);
    }

    public function test_notification_job_skips_sending_if_timestamp_does_not_match(): void
    {
        Notification::fake();

        $band = Bands::factory()->withOwners()->create();
        $booking = Bookings::factory()->create([
            'band_id' => $band->id,
            'enable_portal_media_access' => true,
        ]);

        $contact = Contacts::factory()->create([
            'band_id' => $band->id,
            'can_login' => true,
        ]);
        $booking->contacts()->attach($contact->id);

        $event = Events::create([
            'eventable_type' => 'App\\Models\\Bookings',
            'eventable_id' => $booking->id,
            'event_type_id' => $booking->event_type_id,
            'date' => $booking->date,
            'title' => $booking->name,
            'media_folder_path' => '2024/06/test-event',
            'enable_portal_media_access' => true,
            'key' => (string) Str::uuid(),
        ]);

        MediaFile::factory()->create([
            'band_id' => $band->id,
            'folder_path' => '2024/06/test-event',
        ]);

        // Set cache with different timestamp
        $cacheKey = "event_media_upload_notification:{$event->id}";
        Cache::put($cacheKey, time() + 100, now()->addMinutes(15));

        // Execute job with older timestamp
        $job = new NotifyContactsOfMediaUpload($event->id, time());
        $job->handle();

        // Should not send notification
        Notification::assertNothingSent();
    }

    public function test_notification_job_sends_to_all_contacts_when_timestamp_matches(): void
    {
        Notification::fake();

        $band = Bands::factory()->withOwners()->create();
        $booking = Bookings::factory()->create([
            'band_id' => $band->id,
            'enable_portal_media_access' => true,
        ]);

        $contact1 = Contacts::factory()->create([
            'band_id' => $band->id,
            'can_login' => true,
        ]);
        $contact2 = Contacts::factory()->create([
            'band_id' => $band->id,
            'can_login' => true,
        ]);
        $booking->contacts()->attach([$contact1->id, $contact2->id]);

        $event = Events::create([
            'eventable_type' => 'App\\Models\\Bookings',
            'eventable_id' => $booking->id,
            'event_type_id' => $booking->event_type_id,
            'date' => $booking->date,
            'title' => $booking->name,
            'media_folder_path' => '2024/06/test-event',
            'enable_portal_media_access' => true,
            'key' => (string) Str::uuid(),
        ]);

        MediaFile::factory()->create([
            'band_id' => $band->id,
            'folder_path' => '2024/06/test-event',
        ]);

        // Set cache with matching timestamp
        $timestamp = time();
        $cacheKey = "event_media_upload_notification:{$event->id}";
        Cache::put($cacheKey, $timestamp, now()->addMinutes(15));

        // Execute job with matching timestamp
        $job = new NotifyContactsOfMediaUpload($event->id, $timestamp);
        $job->handle();

        // Should send notification to both contacts
        Notification::assertSentTo($contact1, MediaUploadedNotification::class);
        Notification::assertSentTo($contact2, MediaUploadedNotification::class);

        // Cache should be cleared
        $this->assertNull(Cache::get($cacheKey));
    }

    public function test_notification_job_handles_deleted_events_gracefully(): void
    {
        Notification::fake();

        $eventId = 999; // Non-existent event
        $timestamp = time();

        $cacheKey = "event_media_upload_notification:{$eventId}";
        Cache::put($cacheKey, $timestamp, now()->addMinutes(15));

        $job = new NotifyContactsOfMediaUpload($eventId, $timestamp);
        $job->handle();

        // Should not throw exception and should clear cache
        Notification::assertNothingSent();
        $this->assertNull(Cache::get($cacheKey));
    }

    public function test_no_notifications_sent_when_portal_access_disabled(): void
    {
        Notification::fake();

        $band = Bands::factory()->withOwners()->create();
        $booking = Bookings::factory()->create([
            'band_id' => $band->id,
            'enable_portal_media_access' => true,
        ]);

        $contact = Contacts::factory()->create([
            'band_id' => $band->id,
            'can_login' => true,
        ]);
        $booking->contacts()->attach($contact->id);

        $event = Events::create([
            'eventable_type' => 'App\\Models\\Bookings',
            'eventable_id' => $booking->id,
            'event_type_id' => $booking->event_type_id,
            'date' => $booking->date,
            'title' => $booking->name,
            'media_folder_path' => '2024/06/test-event',
            'enable_portal_media_access' => false, // Disabled
            'key' => (string) Str::uuid(),
        ]);

        // Set cache with matching timestamp
        $timestamp = time();
        $cacheKey = "event_media_upload_notification:{$event->id}";
        Cache::put($cacheKey, $timestamp, now()->addMinutes(15));

        // Execute job
        $job = new NotifyContactsOfMediaUpload($event->id, $timestamp);
        $job->handle();

        // Should not send notification
        Notification::assertNothingSent();
    }

    public function test_notification_email_contains_correct_information(): void
    {
        $band = Bands::factory()->withOwners()->create();
        $booking = Bookings::factory()->create([
            'band_id' => $band->id,
            'enable_portal_media_access' => true,
        ]);

        $contact = Contacts::factory()->create([
            'band_id' => $band->id,
            'can_login' => true,
        ]);

        $event = Events::create([
            'eventable_type' => 'App\\Models\\Bookings',
            'eventable_id' => $booking->id,
            'event_type_id' => $booking->event_type_id,
            'date' => $booking->date,
            'title' => 'Summer Festival',
            'media_folder_path' => '2024/06/summer-festival',
            'enable_portal_media_access' => true,
            'key' => (string) Str::uuid(),
        ]);

        $mediaCount = 5;
        $notification = new MediaUploadedNotification($event, $mediaCount);
        $mailMessage = $notification->toMail($contact);

        // Check subject
        $this->assertEquals("New Media Available for Summer Festival", $mailMessage->subject);

        // Check database array
        $databaseData = $notification->toArray($contact);
        $this->assertEquals($event->id, $databaseData['event_id']);
        $this->assertEquals('Summer Festival', $databaseData['event_title']);
        $this->assertEquals($mediaCount, $databaseData['media_count']);
        $this->assertEquals('2024/06/summer-festival', $databaseData['media_folder_path']);
    }

    public function test_contacts_without_login_access_are_not_notified(): void
    {
        Notification::fake();

        $band = Bands::factory()->withOwners()->create();
        $booking = Bookings::factory()->create([
            'band_id' => $band->id,
            'enable_portal_media_access' => true,
        ]);

        $contactWithAccess = Contacts::factory()->create([
            'band_id' => $band->id,
            'can_login' => true,
        ]);
        $contactWithoutAccess = Contacts::factory()->create([
            'band_id' => $band->id,
            'can_login' => false,
        ]);
        $booking->contacts()->attach([$contactWithAccess->id, $contactWithoutAccess->id]);

        $event = Events::create([
            'eventable_type' => 'App\\Models\\Bookings',
            'eventable_id' => $booking->id,
            'event_type_id' => $booking->event_type_id,
            'date' => $booking->date,
            'title' => $booking->name,
            'media_folder_path' => '2024/06/test-event',
            'enable_portal_media_access' => true,
            'key' => (string) Str::uuid(),
        ]);

        MediaFile::factory()->create([
            'band_id' => $band->id,
            'folder_path' => '2024/06/test-event',
        ]);

        // Set cache and execute job
        $timestamp = time();
        $cacheKey = "event_media_upload_notification:{$event->id}";
        Cache::put($cacheKey, $timestamp, now()->addMinutes(15));

        $job = new NotifyContactsOfMediaUpload($event->id, $timestamp);
        $job->handle();

        // Only contact with login access should be notified
        Notification::assertSentTo($contactWithAccess, MediaUploadedNotification::class);
        Notification::assertNotSentTo($contactWithoutAccess, MediaUploadedNotification::class);
    }
}
