<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Bookings;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Carbon\Carbon;
use OpenApi\Attributes as OA;

class BookingsController extends Controller
{
    #[OA\Get(
        path: '/bookings',
        operationId: 'listBookings',
        summary: 'List bookings',
        description: "Returns all bookings for the authenticated band, ordered by date descending. Includes associated events, contacts, and payments.\n\n**Required permission:** `api:read-bookings`",
        security: [['BearerToken' => []]],
        tags: ['Bookings'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Success',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'success', type: 'boolean', example: true),
                    new OA\Property(property: 'bookings', type: 'array', items: new OA\Items(ref: '#/components/schemas/Booking')),
                    new OA\Property(property: 'total', type: 'integer', example: 25),
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

    #[OA\Post(
        path: '/bookings',
        operationId: 'createBooking',
        summary: 'Create a booking',
        description: "Creates a new booking for the authenticated band. The end time is calculated automatically from `start_time` + `duration` (hours).\n\n**Required permission:** `api:write-bookings`",
        security: [['BearerToken' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'event_type_id', 'date', 'start_time', 'duration', 'price', 'contract_option'],
                allOf: [
                    new OA\Schema(ref: '#/components/schemas/BookingWriteBody'),
                    new OA\Schema(properties: [
                        new OA\Property(property: 'duration', type: 'integer', description: 'Duration in whole hours (1–24). Used to calculate end_time.', minimum: 1, maximum: 24, example: 5),
                    ]),
                ],
            ),
        ),
        tags: ['Bookings'],
        responses: [
            new OA\Response(
                response: 201,
                description: 'Booking created',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'success', type: 'boolean', example: true),
                    new OA\Property(property: 'message', type: 'string', example: 'Booking created successfully'),
                    new OA\Property(property: 'booking', ref: '#/components/schemas/Booking'),
                ]),
            ),
            new OA\Response(response: 401, description: 'Unauthorized', content: new OA\JsonContent(ref: '#/components/schemas/ErrorUnauthorized')),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/ErrorForbidden')),
            new OA\Response(response: 422, description: 'Validation failed', content: new OA\JsonContent(ref: '#/components/schemas/ErrorValidation')),
        ],
    )]
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

    #[OA\Get(
        path: '/bookings/{id}',
        operationId: 'getBooking',
        summary: 'Get a booking',
        description: "Returns a single booking. Returns 404 if the booking does not exist or does not belong to the authenticated band.\n\n**Required permission:** `api:read-bookings`",
        security: [['BearerToken' => []]],
        tags: ['Bookings'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'Booking ID', schema: new OA\Schema(type: 'integer', example: 42)),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Success',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'success', type: 'boolean', example: true),
                    new OA\Property(property: 'booking', ref: '#/components/schemas/Booking'),
                ]),
            ),
            new OA\Response(response: 401, description: 'Unauthorized', content: new OA\JsonContent(ref: '#/components/schemas/ErrorUnauthorized')),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/ErrorForbidden')),
            new OA\Response(response: 404, description: 'Not Found', content: new OA\JsonContent(ref: '#/components/schemas/ErrorNotFound')),
        ],
    )]
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

    #[OA\Put(
        path: '/bookings/{id}',
        operationId: 'replaceBooking',
        summary: 'Replace a booking',
        description: "Full update of an existing booking. Only fields provided are validated and updated.\n\n**Required permission:** `api:write-bookings`",
        security: [['BearerToken' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/BookingWriteBody')),
        tags: ['Bookings'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'Booking ID', schema: new OA\Schema(type: 'integer', example: 42)),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Booking updated',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'success', type: 'boolean', example: true),
                    new OA\Property(property: 'message', type: 'string', example: 'Booking updated successfully'),
                    new OA\Property(property: 'booking', ref: '#/components/schemas/Booking'),
                ]),
            ),
            new OA\Response(response: 401, description: 'Unauthorized', content: new OA\JsonContent(ref: '#/components/schemas/ErrorUnauthorized')),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/ErrorForbidden')),
            new OA\Response(response: 404, description: 'Not Found', content: new OA\JsonContent(ref: '#/components/schemas/ErrorNotFound')),
            new OA\Response(response: 422, description: 'Validation failed', content: new OA\JsonContent(ref: '#/components/schemas/ErrorValidation')),
        ],
    )]
    #[OA\Patch(
        path: '/bookings/{id}',
        operationId: 'updateBooking',
        summary: 'Partially update a booking',
        description: "Partial update — send only the fields you want to change.\n\n**Required permission:** `api:write-bookings`",
        security: [['BearerToken' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/BookingWriteBody')),
        tags: ['Bookings'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'Booking ID', schema: new OA\Schema(type: 'integer', example: 42)),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Booking updated',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'success', type: 'boolean', example: true),
                    new OA\Property(property: 'message', type: 'string', example: 'Booking updated successfully'),
                    new OA\Property(property: 'booking', ref: '#/components/schemas/Booking'),
                ]),
            ),
            new OA\Response(response: 401, description: 'Unauthorized', content: new OA\JsonContent(ref: '#/components/schemas/ErrorUnauthorized')),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/ErrorForbidden')),
            new OA\Response(response: 404, description: 'Not Found', content: new OA\JsonContent(ref: '#/components/schemas/ErrorNotFound')),
            new OA\Response(response: 422, description: 'Validation failed', content: new OA\JsonContent(ref: '#/components/schemas/ErrorValidation')),
        ],
    )]
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

    #[OA\Delete(
        path: '/bookings/{id}',
        operationId: 'deleteBooking',
        summary: 'Delete a booking',
        description: "Permanently deletes a booking. This action cannot be undone.\n\n**Required permission:** `api:write-bookings`",
        security: [['BearerToken' => []]],
        tags: ['Bookings'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'Booking ID', schema: new OA\Schema(type: 'integer', example: 42)),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Booking deleted',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'success', type: 'boolean', example: true),
                    new OA\Property(property: 'message', type: 'string', example: 'Booking deleted successfully'),
                ]),
            ),
            new OA\Response(response: 401, description: 'Unauthorized', content: new OA\JsonContent(ref: '#/components/schemas/ErrorUnauthorized')),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/ErrorForbidden')),
            new OA\Response(response: 404, description: 'Not Found', content: new OA\JsonContent(ref: '#/components/schemas/ErrorNotFound')),
        ],
    )]
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
