<?php

namespace App\Models\Traits;

use Google\Client;
use App\Models\GoogleEvents;
use Google\Service\Calendar;
use App\Models\BandCalendars;
use App\Services\GoogleCalendarService;
use Google\Service\Calendar\Event as GoogleEvent;

trait GoogleCalendarWritable
{
    public function writeToGoogleCalendar(BandCalendars $bandCalendar = null): bool|GoogleEvent
    {
        if (!$bandCalendar) {
            \Log::warning("writeToGoogleCalendar called without bandCalendar");
            return false;
        }

        $googleCalendarService = app(GoogleCalendarService::class);

        // Check for existing event for THIS specific calendar
        $existingGoogleEvent = $this->getGoogleEvent($bandCalendar);

        if ($existingGoogleEvent && $existingGoogleEvent->google_event_id) {
            // Update existing event
            \Log::debug("Updating existing Google Calendar event {$existingGoogleEvent->google_event_id} for " . get_class($this) . " ID: {$this->id}");

            try {
                return $googleCalendarService->updateEvent(
                    $bandCalendar->calendar_id,
                    $existingGoogleEvent->google_event_id,
                    $this->getEventData()
                );
            } catch (\Exception $e) {
                \Log::warning("Failed to update event {$existingGoogleEvent->google_event_id}, checking if event exists in Google: " . $e->getMessage());

                // Event might have been deleted in Google Calendar
                // Remove stale reference
                $existingGoogleEvent->delete();
            }
        }

        // DEFENSIVE CHECK: For Events model, check if ANY GoogleEvents record exists for this event
        // This prevents creating duplicates when called with different calendar IDs
        if (get_class($this) === 'App\\Models\\Events') {
            $anyExistingEvent = $this->googleEvents()
                ->where('band_calendar_id', $bandCalendar->id)
                ->first();

            if ($anyExistingEvent && $anyExistingEvent->google_event_id) {
                \Log::info("Found existing Google Calendar event for Event ID {$this->id} in calendar {$bandCalendar->id}, updating instead of inserting");

                try {
                    return $googleCalendarService->updateEvent(
                        $bandCalendar->calendar_id,
                        $anyExistingEvent->google_event_id,
                        $this->getEventData()
                    );
                } catch (\Exception $e) {
                    \Log::warning("Failed to update found event, will create new: " . $e->getMessage());
                    $anyExistingEvent->delete();
                }
            }
        }

        // Create new event - confirmed no existing event found
        \Log::info("Creating new Google Calendar event for " . get_class($this) . " ID: {$this->id} in calendar {$bandCalendar->id}");
        return $googleCalendarService->insertEvent($bandCalendar->calendar_id, $this->getEventData());
    }

    public function deleteFromGoogleCalendar(BandCalendars $bandCalendar = null): bool
    {
        if (!$bandCalendar || !$this->existsInGoogleCalendar($bandCalendar)) {
            return false;
        }

        $googleCalendarService = app(GoogleCalendarService::class);

        $googleEventId = $this->getGoogleEvent($bandCalendar)->google_event_id;

        $deleted = $googleCalendarService->deleteEvent($bandCalendar->calendar_id, $googleEventId);

        if($deleted)
        {
            $this->removeGoogleEventId($bandCalendar, $googleEventId);
        }


        return $deleted;
    }

    protected function existsInGoogleCalendar(BandCalendars $bandCalendar): bool
    {
        // Logic to check if the event exists in Google Calendar
        return !is_null($this->getGoogleEvent($bandCalendar));
    }

    protected function getEventData(): GoogleEvent
    {
        $event = new GoogleEvent([]);
        $event->setLocation($this->getGoogleCalendarLocation());
        $event->setColorId($this->getGoogleCalendarColor());
        $event->setSummary($this->getGoogleCalendarSummary());
        $event->setDescription($this->getGoogleCalendarDescription());
        $event->setStart($this->getGoogleCalendarStartTime());
        $event->setEnd($this->getGoogleCalendarEndTime());
        
        return $event;
    }

    public function storeGoogleEventId(BandCalendars $bandCalendar, string $googleEventId): GoogleEvents
    {
        \Log::debug("Storing Google Event ID: {$googleEventId} for " . get_class($this) . " ID: {$this->id}, Calendar: {$bandCalendar->type} (ID: {$bandCalendar->id})");

        $googleEvent = GoogleEvents::updateOrCreate(
            [
                'google_eventable_id' => $this->id,
                'google_eventable_type' => get_class($this),
                'band_calendar_id' => $bandCalendar->id
            ],
            ['google_event_id' => $googleEventId]
        );

        if ($googleEvent->wasRecentlyCreated) {
            \Log::info("Created new GoogleEvents record ID: {$googleEvent->id}");
        } elseif ($googleEvent->wasChanged()) {
            \Log::info("Updated GoogleEvents record ID: {$googleEvent->id}");
        }

        // Clear cached relationship data to ensure fresh reads
        $this->unsetRelation('googleEvent');
        $this->unsetRelation('googleEvents');

        return $googleEvent;
    }

    public function removeGoogleEventId(BandCalendars $bandCalendar): bool
    {
        $googleEvent = GoogleEvents::where('google_eventable_id', $this->id)
            ->where('google_eventable_type', get_class($this))
            ->where('band_calendar_id', $bandCalendar->id)
            ->first();

        if ($googleEvent) {
            return $googleEvent->delete();
        }
        return false;
    }
}