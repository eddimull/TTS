<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Bookings;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class BookedDatesController extends Controller
{
    #[OA\Get(
        path: '/booked-dates',
        operationId: 'listBookedDates',
        summary: 'List booked dates',
        description: "Returns bookings for the authenticated band with optional date filtering. Results are ordered by date ascending, then start time ascending.\n\n**Required permission:** `api:read-bookings`",
        security: [['BearerToken' => []]],
        tags: ['Booked Dates'],
        parameters: [
            new OA\Parameter(name: 'date', in: 'query', required: false, description: 'Exact date match (YYYY-MM-DD)', schema: new OA\Schema(type: 'string', format: 'date', example: '2025-06-14')),
            new OA\Parameter(name: 'from', in: 'query', required: false, description: 'Start of date range, inclusive (YYYY-MM-DD)', schema: new OA\Schema(type: 'string', format: 'date', example: '2025-06-01')),
            new OA\Parameter(name: 'to', in: 'query', required: false, description: 'End of date range, inclusive (YYYY-MM-DD)', schema: new OA\Schema(type: 'string', format: 'date', example: '2025-06-30')),
            new OA\Parameter(name: 'before', in: 'query', required: false, description: 'Exclusive upper-bound date (YYYY-MM-DD)', schema: new OA\Schema(type: 'string', format: 'date', example: '2025-07-01')),
            new OA\Parameter(name: 'after', in: 'query', required: false, description: 'Exclusive lower-bound date (YYYY-MM-DD)', schema: new OA\Schema(type: 'string', format: 'date', example: '2025-05-31')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Success',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'success', type: 'boolean', example: true),
                    new OA\Property(property: 'band', ref: '#/components/schemas/BandSummary'),
                    new OA\Property(property: 'bookings', type: 'array', items: new OA\Items(ref: '#/components/schemas/BookedDate')),
                    new OA\Property(property: 'total', type: 'integer', example: 12),
                    new OA\Property(property: 'filters', ref: '#/components/schemas/AppliedFilters'),
                ]),
            ),
            new OA\Response(response: 401, description: 'Unauthorized', content: new OA\JsonContent(ref: '#/components/schemas/ErrorUnauthorized')),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/ErrorForbidden')),
        ],
    )]
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

        // Query bookings directly for this band
        $query = Bookings::where('band_id', $band->id)
            ->with(['eventType', 'events']);

        // Apply date filters — filter against events.date since that column moved to events
        if ($request->has('date')) {
            $query->whereHas('events', fn ($q) => $q->whereDate('date', $validated['date']));
        }

        if ($request->has('from')) {
            $query->whereHas('events', fn ($q) => $q->whereDate('date', '>=', $validated['from']));
        }

        if ($request->has('to')) {
            $query->whereHas('events', fn ($q) => $q->whereDate('date', '<=', $validated['to']));
        }

        if ($request->has('before')) {
            $query->whereHas('events', fn ($q) => $q->whereDate('date', '<', $validated['before']));
        }

        if ($request->has('after')) {
            $query->whereHas('events', fn ($q) => $q->whereDate('date', '>', $validated['after']));
        }

        $bookings = $query->get()
            ->sortBy(fn ($booking) => $booking->start_date?->format('Y-m-d H:i') ?? '')
            ->values()
            ->map(function ($booking) {
                $primaryEvent = $booking->events->sortBy([['date', 'asc'], ['id', 'asc']])->first();
                return [
                    'id' => $booking->id,
                    'name' => $booking->name,
                    'start_date' => $booking->start_date?->format('Y-m-d'),
                    'end_date' => $booking->end_date?->format('Y-m-d'),
                    'event_count' => $booking->event_count,
                    'is_multi_event' => $booking->is_multi_event,
                    'venue_summary' => $booking->venue_summary,
                    'event_type' => $booking->eventType?->name,
                    'event_type_id' => $booking->event_type_id,
                    'status' => $booking->status,
                    'price' => $booking->price,
                    'notes' => $booking->notes,
                    'events' => $booking->events->map(fn ($event) => [
                        'id' => $event->id,
                        'date' => $event->date?->format('Y-m-d'),
                        'start_time' => $event->start_time?->format('H:i'),
                        'end_time' => $event->end_time?->format('H:i'),
                        'venue_name' => $event->venue_name,
                        'venue_address' => $event->venue_address,
                    ])->values(),
                ];
            });

        $response = [
            'success' => true,
            'band' => [
                'id' => $band->id,
                'name' => $band->name,
            ],
            'bookings' => $bookings,
            'total' => $bookings->count(),
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
