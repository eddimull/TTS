<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\BandApi\BandBookingsService;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class BookingsController extends Controller
{
    public function __construct(
        private BandBookingsService $bookings
    ) {}

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

        $bookings = $this->bookings->list($band);

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
        description: "Creates a new booking for the authenticated band, the same way the TTS web and mobile apps do: the booking gets its contract record (seeded with the band's initial contract terms) and one event whose timeline (load in, soundcheck, quiet; plus ceremony and dances for weddings) and details are generated from the event type, assigned to the band's default roster, with the event value equal to the booking price. The end time is calculated from `start_time` + `duration` (hours). `status` defaults to `draft`.\n\nPass the client as `contact` (and/or more in `contacts`): the band's existing contact with that email is reused, otherwise one is created. `is_primary` defaults to true for the first contact.\n\n**Required permission:** `api:write-bookings`",
        security: [['BearerToken' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'event_type_id', 'date', 'start_time', 'duration', 'price', 'contract_option'],
                allOf: [
                    new OA\Schema(ref: '#/components/schemas/BookingWriteBody'),
                    new OA\Schema(properties: [
                        new OA\Property(property: 'duration', type: 'integer', description: 'Duration in whole hours (1–24). Used to calculate end_time.', minimum: 1, maximum: 24, example: 5),
                        new OA\Property(property: 'contact', ref: '#/components/schemas/BookingContactBody', description: 'The client to attach to the booking (optional).'),
                        new OA\Property(property: 'contacts', type: 'array', maxItems: 10, description: 'Additional contacts to attach (optional).', items: new OA\Items(ref: '#/components/schemas/BookingContactBody')),
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

        $validated = $request->validate(BandBookingsService::createRules());

        // No user on token requests: the band's first owner is the author.
        $booking = $this->bookings->create($band, $validated);

        return response()->json([
            'success' => true,
            'message' => 'Booking created successfully',
            'booking' => $this->bookings->format($booking),
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

        $booking = $this->bookings->find($band, $id, withRelations: true);

        if (!$booking) {
            return $this->notFound();
        }

        return response()->json([
            'success' => true,
            'booking' => $this->bookings->format($booking),
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

        $booking = $this->bookings->find($band, $id);

        if (!$booking) {
            return $this->notFound();
        }

        $validated = $request->validate(BandBookingsService::updateRules());

        $booking = $this->bookings->update($booking, $validated);

        return response()->json([
            'success' => true,
            'message' => 'Booking updated successfully',
            'booking' => $this->bookings->format($booking),
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

        $booking = $this->bookings->find($band, $id);

        if (!$booking) {
            return $this->notFound();
        }

        $this->bookings->delete($booking);

        return response()->json([
            'success' => true,
            'message' => 'Booking deleted successfully',
        ]);
    }

    #[OA\Post(
        path: '/bookings/{id}/contacts',
        operationId: 'addBookingContact',
        summary: 'Add a contact to a booking',
        description: "Attaches a client contact to the booking. The band's existing contact with this email is reused (its name and phone are left unchanged); otherwise a new contact is created for the band. Re-adding an attached contact updates its `role` and `is_primary`. `is_primary` defaults to true when the booking has no other primary contact. Returns 404 if the booking does not exist or does not belong to the authenticated band.\n\n**Required permission:** `api:write-bookings`",
        security: [['BearerToken' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/BookingContactBody')),
        tags: ['Bookings'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'Booking ID', schema: new OA\Schema(type: 'integer', example: 42)),
        ],
        responses: [
            new OA\Response(
                response: 201,
                description: 'Contact added',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'success', type: 'boolean', example: true),
                    new OA\Property(property: 'message', type: 'string', example: 'Contact added successfully'),
                    new OA\Property(property: 'booking', ref: '#/components/schemas/Booking'),
                ]),
            ),
            new OA\Response(response: 401, description: 'Unauthorized', content: new OA\JsonContent(ref: '#/components/schemas/ErrorUnauthorized')),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/ErrorForbidden')),
            new OA\Response(response: 404, description: 'Not Found', content: new OA\JsonContent(ref: '#/components/schemas/ErrorNotFound')),
            new OA\Response(response: 422, description: 'Validation failed', content: new OA\JsonContent(ref: '#/components/schemas/ErrorValidation')),
        ],
    )]
    public function storeContact(Request $request, $id)
    {
        $band = $request->input('authenticated_band');

        $booking = $this->bookings->find($band, $id);

        if (!$booking) {
            return $this->notFound();
        }

        $validated = $request->validate(BandBookingsService::contactRules());

        $booking = $this->bookings->addContact($band, $booking, $validated);

        return response()->json([
            'success' => true,
            'message' => 'Contact added successfully',
            'booking' => $this->bookings->format($booking),
        ], 201);
    }

    private function notFound()
    {
        return response()->json([
            'error' => 'Not Found',
            'message' => 'Booking not found or does not belong to your band'
        ], 404);
    }
}
