<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Events;
use App\Models\Bookings;
use Illuminate\Http\Request;

class BookedDatesController extends Controller
{
    /**
     * Get all bookings for the authenticated band
     *
     * Returns bookings (not events) with optional date filtering.
     *
     * Query Parameters:
     * - date: Specific date (YYYY-MM-DD)
     * - from: Start date for range query (YYYY-MM-DD)
     * - to: End date for range query (YYYY-MM-DD)
     * - before: Get bookings before this date (YYYY-MM-DD)
     * - after: Get bookings after this date (YYYY-MM-DD)
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
