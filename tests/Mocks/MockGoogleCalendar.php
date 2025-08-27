<?php 

namespace Tests\Mocks;

class MockGoogleCalendar {
    public function getEvents($calendarId, $timeMin, $timeMax) {
        return [
            [
                'id' => '1',
                'summary' => 'Mock Event 1',
                'start' => ['dateTime' => '2023-10-01T10:00:00Z'],
                'end' => ['dateTime' => '2023-10-01T11:00:00Z']
            ],
            [
                'id' => '2',
                'summary' => 'Mock Event 2',
                'start' => ['dateTime' => '2023-10-02T12:00:00Z'],
                'end' => ['dateTime' => '2023-10-02T13:00:00Z']
            ]
        ];
    }

    public function createEvent($calendarId, $event) {
        return array_merge($event, ['id' => 'mocked_id']);
    }

    public function deleteEvent($calendarId, $eventId) {
        return true;
    }
}