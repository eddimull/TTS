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

        // Use a cache lock to prevent race conditions between concurrent jobs
        $lockKey = 'gcal_write_lock:' . get_class($this) . ':' . $this->id . ':' . $bandCalendar->id;
        $lock = \Cache::lock($lockKey, 10); 

        try {
            if ($lock->block(5)) {
                \Log::debug("Acquired lock for " . get_class($this) . " ID: {$this->id}, calendar: {$bandCalendar->id}");

                $existingGoogleEvent = $this->getGoogleEvent($bandCalendar);

                if ($existingGoogleEvent && $existingGoogleEvent->google_event_id) {

                    \Log::debug("Updating existing Google Calendar event {$existingGoogleEvent->google_event_id} for " . get_class($this) . " ID: {$this->id}");

                    try {
                        $result = $googleCalendarService->updateEvent(
                            $bandCalendar->calendar_id,
                            $existingGoogleEvent->google_event_id,
                            $this->getEventData()
                        );
                        $lock->release();
                        return $result;
                    } catch (\Exception $e) {
                        \Log::warning("Failed to update event {$existingGoogleEvent->google_event_id}, checking if event exists in Google: " . $e->getMessage());

                        $existingGoogleEvent->delete();
                    }
                }

                \Log::info("Creating new Google Calendar event for " . get_class($this) . " ID: {$this->id} in calendar {$bandCalendar->id}");
                $result = $googleCalendarService->insertEvent($bandCalendar->calendar_id, $this->getEventData());
                $lock->release();
                return $result;

            } else {

                \Log::warning("Could not acquire lock for " . get_class($this) . " ID: {$this->id}, calendar: {$bandCalendar->id}. Another job may be processing this event.");

                sleep(1);
                $existingGoogleEvent = $this->getGoogleEvent($bandCalendar);

                if ($existingGoogleEvent && $existingGoogleEvent->google_event_id) {
                    \Log::info("Event was created by another job, updating instead");
                    return $googleCalendarService->updateEvent(
                        $bandCalendar->calendar_id,
                        $existingGoogleEvent->google_event_id,
                        $this->getEventData()
                    );
                }

                \Log::warning("Proceeding with insert after lock timeout for " . get_class($this) . " ID: {$this->id}");
                return $googleCalendarService->insertEvent($bandCalendar->calendar_id, $this->getEventData());
            }
        } catch (\Exception $e) {
            $lock->release();
            throw $e;
        }
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