<?php

namespace App\Services;

use Google\Client;
use Google\Service\Calendar;
use Google\Service\Calendar\Event as GoogleEvent;

class GoogleCalendarService
{
    protected Calendar $calendarService;

    public function __construct(protected Client $client)
    {
        $this->client->setAuthConfig(config('google-calendar.auth_profiles.service_account.credentials_json'));
        $this->client->addScope(Calendar::CALENDAR);
        $this->client->addScope(Calendar::CALENDAR_EVENTS);
        $this->calendarService = new Calendar($this->client);
    }

    public function insertEvent(string $calendarId, GoogleEvent $event): GoogleEvent
    {
        return $this->calendarService->events->insert($calendarId, $event);
    }

    public function updateEvent(string $calendarId, string $eventId, GoogleEvent $event): GoogleEvent
    {
        return $this->calendarService->events->update($calendarId, $eventId, $event);
    }

    public function deleteEvent(string $calendarId, string $eventId): bool
    {
        return $this->calendarService->events->delete($calendarId, $eventId);
    }

    public function getEvent(string $calendarId, string $eventId): GoogleEvent
    {
        return $this->calendarService->events->get($calendarId, $eventId);
    }
}
