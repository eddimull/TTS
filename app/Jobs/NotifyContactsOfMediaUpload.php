<?php

namespace App\Jobs;

use App\Models\Events;
use App\Notifications\MediaUploadedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class NotifyContactsOfMediaUpload implements ShouldQueue
{
    use Queueable;

    /**
     * The event ID for which media was uploaded.
     */
    public int $eventId;

    /**
     * The timestamp when the upload occurred.
     */
    public int $uploadTimestamp;

    /**
     * Create a new job instance.
     */
    public function __construct(int $eventId, int $uploadTimestamp)
    {
        $this->eventId = $eventId;
        $this->uploadTimestamp = $uploadTimestamp;

        // Delay execution by configured time (default 5 minutes)
        $delayMinutes = config('services.media.upload_notification_delay', 5);
        $this->delay(now()->addMinutes($delayMinutes));
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $cacheKey = "event_media_upload_notification:{$this->eventId}";
        $latestTimestamp = Cache::get($cacheKey);

        // Check if this is still the latest upload (no newer uploads have occurred)
        if ($latestTimestamp !== $this->uploadTimestamp) {
            Log::info("Skipping notification for event {$this->eventId} - newer upload detected", [
                'job_timestamp' => $this->uploadTimestamp,
                'latest_timestamp' => $latestTimestamp,
            ]);
            return;
        }

        // Find the event
        $event = Events::find($this->eventId);
        if (!$event) {
            Log::warning("Event {$this->eventId} not found for media upload notification");
            Cache::forget($cacheKey);
            return;
        }

        // Check if portal media access is enabled
        if (!$event->enable_portal_media_access) {
            Log::info("Skipping notification for event {$this->eventId} - portal access disabled");
            Cache::forget($cacheKey);
            return;
        }

        // Get the booking associated with this event
        $booking = null;
        if ($event->eventable_type === 'App\Models\Bookings') {
            $booking = $event->eventable;
        }

        if (!$booking) {
            Log::warning("No booking found for event {$this->eventId}");
            Cache::forget($cacheKey);
            return;
        }

        // Get all contacts for this booking
        $contacts = $booking->contacts()->where('can_login', true)->get();

        if ($contacts->isEmpty()) {
            Log::info("No contacts with login access for event {$this->eventId}");
            Cache::forget($cacheKey);
            return;
        }

        // Count media files in the event folder
        $mediaCount = \App\Models\MediaFile::where('band_id', $event->eventable->band_id)
            ->where('folder_path', $event->media_folder_path)
            ->count();

        // Send notification to each contact
        foreach ($contacts as $contact) {
            try {
                $contact->notify(new MediaUploadedNotification($event, $mediaCount));
                Log::info("Sent media upload notification to contact {$contact->id} for event {$this->eventId}");
            } catch (\Exception $e) {
                Log::error("Failed to send media upload notification to contact {$contact->id}: {$e->getMessage()}");
            }
        }

        // Clear the cache after successful notification
        Cache::forget($cacheKey);
        Log::info("Completed media upload notification for event {$this->eventId}", [
            'contacts_notified' => $contacts->count(),
            'media_count' => $mediaCount,
        ]);
    }
}
