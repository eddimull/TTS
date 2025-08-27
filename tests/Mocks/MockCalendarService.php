<?php

namespace Tests\Mocks;

class MockCalendarService {
    private $mockGoogleCalendar;

    public function __construct() {
        $this->mockGoogleCalendar = new MockGoogleCalendar();
    }

    public function createEvent($eventDetails) {
        return $this->mockGoogleCalendar->simulateCreateEvent($eventDetails);
    }

    public function getEvents($startDate, $endDate) {
        return $this->mockGoogleCalendar->simulateGetEvents($startDate, $endDate);
    }

    public function deleteEvent($eventId) {
        return $this->mockGoogleCalendar->simulateDeleteEvent($eventId);
    }

    public function updateEvent($eventId, $eventDetails) {
        return $this->mockGoogleCalendar->simulateUpdateEvent($eventId, $eventDetails);
    }
}