<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Events;
use App\Models\Bookings;
use App\Models\BandEvents;
use Illuminate\Http\Request;

class EventsController extends Controller
{
    /**
     * Get all events for the authenticated band
     *
     * Returns events from both bookings and band_events with optional date filtering.
     *
     * Query Parameters:
     * - date: Specific date (YYYY-MM-DD)
     * - from: Start date for range query (YYYY-MM-DD)
     * - to: End date for range query (YYYY-MM-DD)
     * - before: Get events before this date (YYYY-MM-DD)
     * - after: Get events after this date (YYYY-MM-DD)
     */
    public function index(Request $request)
    {
        $band = $request->input('authenticated_band');

        if (!$band) {
            return response()->json([
                'error' => 'Band not found',
                'message' => 'Unable to identify band from API token'
            ], 400);
        }

        // Validate date parameters
        $validated = $request->validate([
            'date' => 'nullable|date_format:Y-m-d',
            'from' => 'nullable|date_format:Y-m-d',
            'to' => 'nullable|date_format:Y-m-d',
            'before' => 'nullable|date_format:Y-m-d',
            'after' => 'nullable|date_format:Y-m-d',
        ]);

        // Query events for this band through both bookings and band_events (polymorphic relationship)
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

        // Apply date filters
        if ($request->has('date')) {
            // Exact date match
            $query->whereDate('date', $validated['date']);
        }

        if ($request->has('from')) {
            // Start of date range
            $query->whereDate('date', '>=', $validated['from']);
        }

        if ($request->has('to')) {
            // End of date range
            $query->whereDate('date', '<=', $validated['to']);
        }

        if ($request->has('before')) {
            // Before a specific date
            $query->whereDate('date', '<', $validated['before']);
        }

        if ($request->has('after')) {
            // After a specific date
            $query->whereDate('date', '>', $validated['after']);
        }

        $events = $query->orderBy('date', 'asc')
            ->orderBy('time', 'asc')
            ->get()
            ->map(function ($event) {
                $date = is_string($event->date) ? $event->date : $event->date->format('Y-m-d');
                $time = null;

                if ($event->time) {
                    $time = is_string($event->time) ? $event->time : $event->time->format('H:i');
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
                    'venue_name' => $event->eventable?->venue_name ?? null,
                    'venue_address' => $event->eventable?->venue_address ?? null,
                    'status' => $event->eventable?->status ?? null,
                    'price' => $event->eventable?->price ?? null,
                    'notes' => $event->notes,
                    'is_public' => isset($event->additional_data->public)
                        && in_array($event->additional_data->public, [1, true, '1', 'true'], true),
                ];
            });

        $response = [
            'success' => true,
            'band' => [
                'id' => $band->id,
                'name' => $band->name,
            ],
            'events' => $events,
            'total' => $events->count(),
        ];

        // Include filter information in response if any filters were applied
        $appliedFilters = [];
        if ($request->has('date')) {
            $appliedFilters['date'] = $validated['date'];
        }
        if ($request->has('from')) {
            $appliedFilters['from'] = $validated['from'];
        }
        if ($request->has('to')) {
            $appliedFilters['to'] = $validated['to'];
        }
        if ($request->has('before')) {
            $appliedFilters['before'] = $validated['before'];
        }
        if ($request->has('after')) {
            $appliedFilters['after'] = $validated['after'];
        }

        if (!empty($appliedFilters)) {
            $response['filters'] = $appliedFilters;
        }

        return response()->json($response);
    }
}
