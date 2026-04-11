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
            ->with(['eventType']);

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

        $bookings = $query->orderBy('date', 'asc')
            ->orderBy('start_time', 'asc')
            ->get()
            ->map(function ($booking) {
                return [
                    'id' => $booking->id,
                    'name' => $booking->name,
                    'date' => $booking->date->format('Y-m-d'),
                    'start_time' => $booking->start_time?->format('H:i'),
                    'end_time' => $booking->end_time?->format('H:i'),
                    'duration' => $booking->duration,
                    'event_type' => $booking->eventType?->name,
                    'event_type_id' => $booking->event_type_id,
                    'venue_name' => $booking->venue_name,
                    'venue_address' => $booking->venue_address,
                    'status' => $booking->status,
                    'price' => $booking->price,
                    'notes' => $booking->notes,
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
