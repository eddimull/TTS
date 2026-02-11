<?php

namespace App\Console\Commands;

use App\Models\Bands;
use App\Models\Events;
use App\Models\BandCalendars;
use App\Services\GoogleCalendarService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CleanDuplicateGoogleCalendarEvents extends Command
{
    protected $signature = 'google-calendar:clean-duplicates
                            {--calendar-type=event : Calendar type to clean (event, public, booking)}
                            {--band-id= : Specific band ID to clean (optional, defaults to all bands)}
                            {--dry-run : Preview duplicates without deleting}';

    protected $description = 'Clean duplicate Google Calendar events that were created on booking/event updates';

    protected GoogleCalendarService $googleCalendarService;
    protected bool $dryRun = false;
    protected int $duplicatesFound = 0;
    protected int $eventsKept = 0;
    protected int $eventsDeleted = 0;

    public function handle()
    {
        $this->googleCalendarService = app(GoogleCalendarService::class);
        $this->dryRun = $this->option('dry-run');
        $calendarType = $this->option('calendar-type');
        $bandId = $this->option('band-id');

        if ($this->dryRun) {
            $this->warn('DRY RUN MODE - No events will be deleted');
        }

        $this->info('Starting duplicate cleanup for calendar type: ' . $calendarType);

        // Get bands to process
        $bands = $bandId ? Bands::where('id', $bandId)->get() : Bands::all();

        foreach ($bands as $band) {
            $this->info("Processing band: {$band->name} (ID: {$band->id})");
            $this->processBand($band, $calendarType);
        }

        $this->newLine();
        $this->info('Summary:');
        $this->line("  Duplicate groups found: {$this->duplicatesFound}");
        $this->line("  Events kept: {$this->eventsKept}");
        $this->line("  Events deleted: {$this->eventsDeleted}");

        if ($this->dryRun) {
            $this->warn('This was a DRY RUN. Run without --dry-run to actually delete duplicates.');
        }

        return 0;
    }

    protected function processBand(Bands $band, string $calendarType)
    {
        // Get the appropriate calendar
        $calendar = match ($calendarType) {
            'event' => $band->eventCalendar,
            'public' => $band->publicCalendar,
            'booking' => $band->bookingCalendar,
            default => null
        };

        if (!$calendar) {
            $this->warn("  No {$calendarType} calendar found for band {$band->name}");
            return;
        }

        $this->line("  Processing {$calendarType} calendar (ID: {$calendar->id})");

        // Get all events for this band
        $events = Events::whereHas('eventable', function ($query) use ($band) {
            $query->where('band_id', $band->id);
        })->get();

        if ($events->isEmpty()) {
            $this->line("    No events found for this band");
            return;
        }

        // Fetch all Google Calendar events for the next year
        $timeMin = now()->subMonths(6);
        $timeMax = now()->addYear();

        try {
            $googleEvents = $this->listEventsFromGoogleCalendar(
                $calendar->calendar_id,
                $timeMin->toRfc3339String(),
                $timeMax->toRfc3339String()
            );

            $this->line("    Found " . count($googleEvents) . " events in Google Calendar");

            // Group events by (title, start time, end time) to find duplicates
            $duplicates = $this->findDuplicates($googleEvents);

            if (empty($duplicates)) {
                $this->line("    No duplicates found");
                return;
            }

            $this->line("    Found " . count($duplicates) . " duplicate groups");

            // Process each duplicate group
            foreach ($duplicates as $key => $duplicateGroup) {
                $this->processDuplicateGroup($duplicateGroup, $events, $calendar);
            }

        } catch (\Exception $e) {
            $this->error("    Failed to fetch events: " . $e->getMessage());
            Log::error("Failed to fetch Google Calendar events for band {$band->id}: " . $e->getMessage());
        }
    }

    protected function listEventsFromGoogleCalendar(string $calendarId, string $timeMin, string $timeMax): array
    {
        $events = [];
        $pageToken = null;

        do {
            $optParams = [
                'timeMin' => $timeMin,
                'timeMax' => $timeMax,
                'singleEvents' => true,
                'orderBy' => 'startTime',
                'maxResults' => 250,
            ];

            if ($pageToken) {
                $optParams['pageToken'] = $pageToken;
            }

            $results = $this->googleCalendarService->getService()->events->listEvents($calendarId, $optParams);

            foreach ($results->getItems() as $event) {
                $events[] = $event;
            }

            $pageToken = $results->getNextPageToken();

        } while ($pageToken);

        return $events;
    }

    protected function findDuplicates(array $events): array
    {
        $grouped = [];

        foreach ($events as $event) {
            $summary = $event->getSummary() ?? 'No Title';
            $start = $event->getStart()->getDateTime() ?? $event->getStart()->getDate();
            $end = $event->getEnd()->getDateTime() ?? $event->getEnd()->getDate();

            $key = md5($summary . $start . $end);

            if (!isset($grouped[$key])) {
                $grouped[$key] = [];
            }

            $grouped[$key][] = $event;
        }

        // Filter to only groups with duplicates
        return array_filter($grouped, fn($group) => count($group) > 1);
    }

    protected function processDuplicateGroup(array $duplicateGroup, $events, BandCalendars $calendar)
    {
        $this->duplicatesFound++;

        $firstEvent = $duplicateGroup[0];
        $summary = $firstEvent->getSummary() ?? 'No Title';
        $start = $firstEvent->getStart()->getDateTime() ?? $firstEvent->getStart()->getDate();

        $this->line("    Duplicate: '{$summary}' at {$start} ({" . count($duplicateGroup) . "} copies)");

        // Try to find which event should be kept based on google_events table
        $storedEventId = $this->findStoredEventId($events, $duplicateGroup, $calendar);

        $eventToKeep = null;

        if ($storedEventId) {
            // Keep the event that matches our stored ID
            foreach ($duplicateGroup as $event) {
                if ($event->getId() === $storedEventId) {
                    $eventToKeep = $event;
                    break;
                }
            }
        }

        // If no stored ID found, keep the most recent event (highest created time)
        if (!$eventToKeep) {
            usort($duplicateGroup, function ($a, $b) {
                $aCreated = $a->getCreated() ? strtotime($a->getCreated()) : 0;
                $bCreated = $b->getCreated() ? strtotime($b->getCreated()) : 0;
                return $bCreated <=> $aCreated; // Descending order
            });
            $eventToKeep = $duplicateGroup[0];
            $this->line("      No stored ID found, keeping most recent event: {$eventToKeep->getId()}");
        } else {
            $this->line("      Keeping event matching stored ID: {$eventToKeep->getId()}");
        }

        // Delete all others
        foreach ($duplicateGroup as $event) {
            if ($event->getId() === $eventToKeep->getId()) {
                $this->eventsKept++;
                continue;
            }

            if ($this->dryRun) {
                $this->line("      [DRY RUN] Would delete: {$event->getId()}");
                $this->eventsDeleted++;
            } else {
                try {
                    $this->deleteDuplicateEvent($calendar->calendar_id, $event->getId());
                    $this->line("      Deleted: {$event->getId()}");
                    $this->eventsDeleted++;
                } catch (\Exception $e) {
                    $this->error("      Failed to delete {$event->getId()}: " . $e->getMessage());
                    Log::error("Failed to delete duplicate event {$event->getId()}: " . $e->getMessage());
                }
            }
        }
    }

    protected function findStoredEventId($events, array $duplicateGroup, BandCalendars $calendar): ?string
    {
        // Extract all event IDs from the duplicate group
        $googleEventIds = array_map(fn($e) => $e->getId(), $duplicateGroup);

        // Check if any of our Events have a stored google_event_id matching these
        foreach ($events as $event) {
            $googleEvent = $event->getGoogleEvent($calendar);
            if ($googleEvent && in_array($googleEvent->google_event_id, $googleEventIds)) {
                return $googleEvent->google_event_id;
            }
        }

        return null;
    }

    protected function deleteDuplicateEvent(string $calendarId, string $eventId): bool
    {
        return $this->googleCalendarService->deleteEvent($calendarId, $eventId);
    }
}
