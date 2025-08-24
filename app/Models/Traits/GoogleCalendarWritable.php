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
            return false;
        }

        $googleCalendarService = app(GoogleCalendarService::class);

        if ($this->existsInGoogleCalendar($bandCalendar)) {
            return $googleCalendarService->updateEvent($bandCalendar->calendar_id, $this->getGoogleEvent($bandCalendar)->google_event_id, $this->getEventData());
        }

        return $googleCalendarService->insertEvent($bandCalendar->calendar_id, $this->getEventData());
    }

    public function deleteFromGoogleCalendar(BandCalendars $bandCalendar = null): bool
    {
        if (!$bandCalendar || !$this->existsInGoogleCalendar($bandCalendar)) {
            return false;
        }

        $googleCalendarService = app(GoogleCalendarService::class);

        return $googleCalendarService->deleteEvent($bandCalendar->calendar_id, $this->getGoogleEvent($bandCalendar)->google_event_id);
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
        \Log::debug('Event Data: ' . json_encode($event));
        return $event;
    }

    public function storeGoogleEventId(BandCalendars $bandCalendar, string $googleEventId): GoogleEvents
    {
        return GoogleEvents::updateOrCreate(
            [
                'google_eventable_id' => $this->id,
                'google_eventable_type' => get_class($this),
                'band_calendar_id' => $bandCalendar->id
            ],
            ['google_event_id' => $googleEventId]
        );
    }
}