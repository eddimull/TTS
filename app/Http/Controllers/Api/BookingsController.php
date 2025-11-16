<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Bookings;
use App\Models\EventTypes;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Carbon\Carbon;

class BookingsController extends Controller
{
    /**
     * Display a listing of bookings for the authenticated band
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

        $bookings = $band->bookings()
            ->with(['events', 'contacts', 'payments'])
            ->orderBy('date', 'desc')
            ->get()
            ->map(function ($booking) {
                return $this->formatBooking($booking);
            });

        return response()->json([
            'success' => true,
            'bookings' => $bookings,
            'total' => $bookings->count(),
        ]);
    }

    /**
     * Store a newly created booking
     */
    public function store(Request $request)
    {
        $band = $request->input('authenticated_band');

        if (!$band) {
            return response()->json([
                'error' => 'Band not found',
                'message' => 'Unable to identify band from API token'
            ], 400);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'event_type_id' => ['required', Rule::exists('event_types', 'id')],
            'date' => 'required|date|after_or_equal:today',
            'start_time' => 'required|date_format:H:i',
            'duration' => 'required|integer|min:1|max:24',
            'price' => 'required|numeric|min:0',
            'venue_name' => 'nullable|string|max:255',
            'venue_address' => 'nullable|string',
            'contract_option' => 'required|in:default,none,external',
            'status' => 'nullable|in:draft,pending,confirmed,cancelled',
            'notes' => 'nullable|string',
        ]);

        // Calculate end_time from duration
        $startTime = Carbon::parse($validated['date'] . ' ' . $validated['start_time']);
        $endTime = $startTime->copy()->addHours($validated['duration']);
        $validated['end_time'] = $endTime->format('H:i');
        unset($validated['duration']);

        // Add band_id and set defaults
        $validated['band_id'] = $band->id;
        $validated['status'] = $validated['status'] ?? 'draft';
        // Use the first band owner as the author for API-created bookings
        $validated['author_id'] = $band->owners->first()?->user_id ?? $band->members->first()?->user_id;

        $booking = Bookings::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Booking created successfully',
            'booking' => $this->formatBooking($booking->load(['events', 'contacts', 'payments'])),
        ], 201);
    }

    /**
     * Display the specified booking
     */
    public function show(Request $request, $id)
    {
        $band = $request->input('authenticated_band');

        $booking = Bookings::where('id', $id)
            ->where('band_id', $band->id)
            ->with(['events', 'contacts', 'payments'])
            ->first();

        if (!$booking) {
            return response()->json([
                'error' => 'Not Found',
                'message' => 'Booking not found or does not belong to your band'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'booking' => $this->formatBooking($booking),
        ]);
    }

    /**
     * Update the specified booking
     */
    public function update(Request $request, $id)
    {
        $band = $request->input('authenticated_band');

        $booking = Bookings::where('id', $id)
            ->where('band_id', $band->id)
            ->first();

        if (!$booking) {
            return response()->json([
                'error' => 'Not Found',
                'message' => 'Booking not found or does not belong to your band'
            ], 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'event_type_id' => ['sometimes', 'required', Rule::exists('event_types', 'id')],
            'date' => 'sometimes|required|date',
            'start_time' => 'sometimes|required|date_format:H:i',
            'end_time' => 'sometimes|required|date_format:H:i',
            'price' => 'sometimes|required|numeric|min:0',
            'venue_name' => 'nullable|string|max:255',
            'venue_address' => 'nullable|string',
            'contract_option' => 'sometimes|required|in:default,none,external',
            'status' => 'nullable|in:draft,pending,confirmed,cancelled',
            'notes' => 'nullable|string',
        ]);

        $booking->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Booking updated successfully',
            'booking' => $this->formatBooking($booking->fresh(['events', 'contacts', 'payments'])),
        ]);
    }

    /**
     * Remove the specified booking
     */
    public function destroy(Request $request, $id)
    {
        $band = $request->input('authenticated_band');

        $booking = Bookings::where('id', $id)
            ->where('band_id', $band->id)
            ->first();

        if (!$booking) {
            return response()->json([
                'error' => 'Not Found',
                'message' => 'Booking not found or does not belong to your band'
            ], 404);
        }

        $booking->delete();

        return response()->json([
            'success' => true,
            'message' => 'Booking deleted successfully',
        ]);
    }

    /**
     * Format booking for API response
     */
    private function formatBooking($booking)
    {
        return [
            'id' => $booking->id,
            'name' => $booking->name,
            'date' => $booking->date->format('Y-m-d'),
            'start_time' => $booking->start_time?->format('H:i'),
            'end_time' => $booking->end_time?->format('H:i'),
            'venue_name' => $booking->venue_name,
            'venue_address' => $booking->venue_address,
            'price' => $booking->price,
            'status' => $booking->status,
            'contract_option' => $booking->contract_option,
            'notes' => $booking->notes,
            'event_type_id' => $booking->event_type_id,
            'created_at' => $booking->created_at->toISOString(),
            'updated_at' => $booking->updated_at->toISOString(),
            'events' => $booking->events ?? [],
            'contacts' => $booking->contacts ?? [],
            'payments' => $booking->payments ?? [],
            'amount_paid' => $booking->amount_paid ?? 0,
            'amount_due' => $booking->amount_due ?? 0,
            'is_paid' => $booking->is_paid ?? false,
        ];
    }
}
