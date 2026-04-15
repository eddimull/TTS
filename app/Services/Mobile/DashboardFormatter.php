<?php

namespace App\Services\Mobile;

use App\Models\Bookings;
use App\Models\BandEvents;

class DashboardFormatter
{
    public function normalizeEvent(mixed $e): array
    {
        $e = is_object($e) && method_exists($e, 'toArray') ? $e->toArray() : (array) $e;

        $source = $e['event_source'] ?? match (true) {
            str_contains($e['eventable_type'] ?? '', 'Rehearsal') => 'rehearsal',
            str_contains($e['eventable_type'] ?? '', 'Booking')   => 'booking',
            default                                                => 'band_event',
        };

        $date = $e['date'] ?? null;
        if ($date && !is_string($date)) {
            $date = is_array($date) ? ($date['date'] ?? null) : (string) $date;
        }
        if ($date && strlen($date) > 10) {
            $date = substr($date, 0, 10);
        }

        $time = $e['time'] ?? null;
        if ($time && !is_string($time)) {
            $time = is_array($time) ? ($time['time'] ?? null) : (string) $time;
        }
        if ($time && strlen($time) > 5) {
            $time = substr($time, 0, 5);
        }

        return [
            'id'              => $e['id'] ?? null,
            'key'             => $e['key'] ?? null,
            'title'           => $e['title'] ?? $e['booking_name'] ?? 'Untitled',
            'date'            => $date,
            'time'            => $time,
            'event_type'      => $e['event_type_name'] ?? null,
            'event_source'    => $source,
            'venue_name'      => $e['venue_name'] ?? null,
            'venue_address'   => $e['venue_address'] ?? null,
            'status'          => $e['status'] ?? null,
            'live_session_id' => $e['live_session_id'] ?? null,
        ];
    }
}
