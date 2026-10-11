<?php

namespace App\Services\BandApi;

use App\Models\BandEvents;
use App\Models\Bands;
use App\Models\Bookings;
use App\Models\Events;
use Illuminate\Support\Collection;

/**
 * A band's events — from bookings and legacy band events (polymorphic) — in
 * the developer API's `/events` row shape. Shared by the REST endpoint and the
 * MCP `list_events` tool.
 */
class BandEventsQuery
{
    /**
     * @param  array<string, mixed>  $filters  validated {@see DateFilters::rules()} input
     * @return Collection<int, array<string, mixed>>
     */
    public function forBand(Bands $band, array $filters = []): Collection
    {
        $query = Events::where(function ($query) use ($band) {
            $query->where(function ($q) use ($band) {
                // Events from bookings
                $q->where('eventable_type', Bookings::class)
                    ->whereHas('eventable', function ($bookingQuery) use ($band) {
                        $bookingQuery->where('band_id', $band->id);
                    });
            })->orWhere(function ($q) use ($band) {
                // Events from band_events
                $q->where('eventable_type', BandEvents::class)
                    ->whereHas('eventable', function ($bandEventQuery) use ($band) {
                        $bandEventQuery->where('band_id', $band->id);
                    });
            });
        })->with(['eventable', 'type']);

        foreach (DateFilters::constraints($filters) as [$operator, $value]) {
            $query->whereDate('date', $operator, $value);
        }

        return $query->orderBy('date', 'asc')
            ->orderBy('start_time', 'asc')
            ->get()
            ->map(fn (Events $event) => $this->format($event));
    }

    /**
     * @return array<string, mixed>
     */
    public function format(Events $event): array
    {
        $date = is_string($event->date) ? $event->date : $event->date->format('Y-m-d');
        $time = null;

        if ($event->start_time) {
            $time = $event->start_time->format('H:i');
        }

        return [
            'id' => $event->id,
            'title' => $event->title,
            'date' => $date,
            'time' => $time,
            'start_datetime' => $event->startDateTime,
            'end_datetime' => $event->endDateTime,
            'event_type' => $event->type?->name,
            'event_type_id' => $event->event_type_id,
            'eventable_type' => class_basename($event->eventable_type),
            'eventable_id' => $event->eventable_id,
            'venue_name' => $event->venue_name,
            'venue_address' => $event->venue_address,
            'status' => $event->eventable?->status ?? null,
            'price' => $event->eventable?->price ?? null,
            'notes' => $event->notes,
            'is_public' => isset($event->additional_data->public)
                && in_array($event->additional_data->public, [1, true, '1', 'true'], true),
        ];
    }
}
