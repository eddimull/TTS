<?php

namespace App\Services\Mobile;

use App\Models\Bands;
use App\Models\Bookings;
use App\Models\BandEvents;

class DashboardFormatter
{
    /**
     * Map of band_id => Bands model, used to attach the `band` chip to each event.
     * Populated internally by formatEvents() before each event is normalized.
     */
    private array $bandLookup = [];

    /**
     * Format a collection/iterable of dashboard events for the mobile API.
     *
     * Internally batches a single Bands lookup query so each event can include
     * its `band` chip without N+1 queries, then normalizes each event into the
     * mobile response shape.
     *
     * @return array<int, array<string, mixed>>
     */
    public function formatEvents(iterable $events): array
    {
        $eventsArray = is_array($events) ? $events : iterator_to_array($events, false);

        $this->loadBandLookup($eventsArray);

        $normalized = [];
        foreach ($eventsArray as $e) {
            $normalized[] = $this->normalizeEvent($e);
        }
        return $normalized;
    }

    /**
     * Preload bands for the events being formatted so each event can include the
     * band chip without N+1 queries. Pass any iterable of events that carry a
     * `band_id` (array key or object property).
     */
    private function loadBandLookup(iterable $events): void
    {
        $bandIds = [];
        foreach ($events as $e) {
            $bid = is_array($e) ? ($e['band_id'] ?? null) : ($e->band_id ?? null);
            if ($bid !== null) {
                $bandIds[(int) $bid] = true;
            }
        }
        if (empty($bandIds)) {
            $this->bandLookup = [];
            return;
        }
        $this->bandLookup = Bands::whereIn('id', array_keys($bandIds))
            ->get()
            ->keyBy('id')
            ->all();
    }

    private function normalizeEvent(mixed $e): array
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
            'band'            => $this->formatBand($e['band_id'] ?? null),
        ];
    }

    private function formatBand(mixed $bandId): ?array
    {
        if ($bandId === null) {
            return null;
        }
        $band = $this->bandLookup[(int) $bandId] ?? null;
        if (!$band) {
            return null;
        }
        return [
            'id'          => $band->id,
            'name'        => $band->name,
            'is_personal' => (bool) $band->is_personal,
            'logo_url'    => TokenService::resolveLogoUrl($band->logo),
        ];
    }
}
