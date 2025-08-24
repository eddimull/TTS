<?php

namespace App\Models\Interfaces;

use App\Models\BandCalendars;
use App\Models\GoogleEvents;

interface GoogleCalenderable
{
    public function getGoogleEvent(BandCalendars $bandCalendar): GoogleEvents|null;
    public function getGoogleCalendar(): BandCalendars|null;
    public function getGoogleCalendarSummary(): string|null;
    public function getGoogleCalendarDescription(): string|null;
    public function getGoogleCalendarStartTime(): \Google\Service\Calendar\EventDateTime;
    public function getGoogleCalendarLocation(): string|null;
    public function getGoogleCalendarColor(): string|null;
    public function getGoogleCalendarEndTime(): \Google\Service\Calendar\EventDateTime;
    public function writeToGoogleCalendar(BandCalendars $bandCalendar): bool|\Google\Service\Calendar\Event;
}
