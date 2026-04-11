<?php

namespace App;

use OpenApi\Attributes as OA;

#[OA\Info(
    title: 'TTS Bandmate API',
    version: '1.0.0',
    description: <<<'DESC'
The TTS Bandmate Band API allows external integrations to read and manage bookings
and events for a band. Authentication uses a **Bearer token** issued per band.

## Authentication

All endpoints require an `Authorization: Bearer <token>` header. Tokens are
scoped to a single band and carry fine-grained permissions.

## Permissions

Each token is granted one or more permissions at issuance:

| Permission | Grants access to |
|---|---|
| `api:read-bookings` | GET /booked-dates, GET /bookings, GET /bookings/{id} |
| `api:write-bookings` | POST /bookings, PUT /bookings/{id}, PATCH /bookings/{id}, DELETE /bookings/{id} |
| `api:read-events` | GET /events |

## Date Filtering

`/booked-dates` and `/events` support the same set of query parameters for filtering
by date. All dates must be in `YYYY-MM-DD` format. Parameters can be combined
(e.g. `from` + `to` for a range).
DESC,
    contact: new OA\Contact(name: 'TTS Bandmate'),
)]
#[OA\Server(url: L5_SWAGGER_CONST_HOST, description: 'API Server')]
#[OA\SecurityScheme(
    securityScheme: 'BearerToken',
    type: 'http',
    scheme: 'bearer',
    description: 'Band API token issued from the TTS dashboard',
)]
#[OA\Tag(name: 'Bookings', description: 'Create and manage bookings for your band')]
#[OA\Tag(name: 'Booked Dates', description: 'Query booking schedules with flexible date filters')]
#[OA\Tag(name: 'Events', description: 'Read calendar events (bookings + legacy band events)')]
// ── Shared schemas ────────────────────────────────────────────────────────────
#[OA\Schema(
    schema: 'BandSummary',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'name', type: 'string', example: 'The Groove Collective'),
    ],
)]
#[OA\Schema(
    schema: 'AppliedFilters',
    description: 'Echoes back any date filters that were applied',
    properties: [
        new OA\Property(property: 'date', type: 'string', format: 'date'),
        new OA\Property(property: 'from', type: 'string', format: 'date'),
        new OA\Property(property: 'to', type: 'string', format: 'date'),
        new OA\Property(property: 'before', type: 'string', format: 'date'),
        new OA\Property(property: 'after', type: 'string', format: 'date'),
    ],
)]
#[OA\Schema(
    schema: 'Booking',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 42),
        new OA\Property(property: 'name', type: 'string', example: 'Smith Wedding Reception'),
        new OA\Property(property: 'date', type: 'string', format: 'date', example: '2025-06-14'),
        new OA\Property(property: 'start_time', type: 'string', example: '18:00', nullable: true),
        new OA\Property(property: 'end_time', type: 'string', example: '23:00', nullable: true),
        new OA\Property(property: 'venue_name', type: 'string', example: 'Grand Ballroom', nullable: true),
        new OA\Property(property: 'venue_address', type: 'string', example: '123 Main St, Nashville, TN 37201', nullable: true),
        new OA\Property(property: 'price', type: 'number', format: 'float', example: 3500.00),
        new OA\Property(property: 'status', type: 'string', enum: ['draft', 'pending', 'confirmed', 'cancelled'], example: 'confirmed'),
        new OA\Property(property: 'contract_option', type: 'string', enum: ['default', 'none', 'external'], example: 'default'),
        new OA\Property(property: 'notes', type: 'string', nullable: true),
        new OA\Property(property: 'event_type_id', type: 'integer', example: 2),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time', example: '2025-03-01T12:00:00.000000Z'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time', example: '2025-03-15T09:30:00.000000Z'),
        new OA\Property(property: 'events', type: 'array', items: new OA\Items(type: 'object'), description: 'Associated calendar events'),
        new OA\Property(property: 'contacts', type: 'array', items: new OA\Items(type: 'object'), description: 'Client contacts'),
        new OA\Property(property: 'payments', type: 'array', items: new OA\Items(type: 'object'), description: 'Recorded payments'),
        new OA\Property(property: 'amount_paid', type: 'number', format: 'float', example: 1750.00),
        new OA\Property(property: 'amount_due', type: 'number', format: 'float', example: 1750.00),
        new OA\Property(property: 'is_paid', type: 'boolean', example: false),
    ],
)]
#[OA\Schema(
    schema: 'BookedDate',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 42),
        new OA\Property(property: 'name', type: 'string', example: 'Smith Wedding Reception'),
        new OA\Property(property: 'date', type: 'string', format: 'date', example: '2025-06-14'),
        new OA\Property(property: 'start_time', type: 'string', example: '18:00', nullable: true),
        new OA\Property(property: 'end_time', type: 'string', example: '23:00', nullable: true),
        new OA\Property(property: 'duration', type: 'integer', description: 'Duration in hours', example: 5, nullable: true),
        new OA\Property(property: 'event_type', type: 'string', example: 'Wedding', nullable: true),
        new OA\Property(property: 'event_type_id', type: 'integer', example: 2, nullable: true),
        new OA\Property(property: 'venue_name', type: 'string', example: 'Grand Ballroom', nullable: true),
        new OA\Property(property: 'venue_address', type: 'string', example: '123 Main St, Nashville, TN 37201', nullable: true),
        new OA\Property(property: 'status', type: 'string', enum: ['draft', 'pending', 'confirmed', 'cancelled'], example: 'confirmed'),
        new OA\Property(property: 'price', type: 'number', format: 'float', example: 3500.00),
        new OA\Property(property: 'notes', type: 'string', nullable: true),
    ],
)]
#[OA\Schema(
    schema: 'Event',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 101),
        new OA\Property(property: 'title', type: 'string', example: 'Smith Wedding Reception'),
        new OA\Property(property: 'date', type: 'string', format: 'date', example: '2025-06-14'),
        new OA\Property(property: 'time', type: 'string', example: '18:00', nullable: true),
        new OA\Property(property: 'start_datetime', type: 'string', format: 'date-time', example: '2025-06-14T18:00:00.000000Z', nullable: true),
        new OA\Property(property: 'end_datetime', type: 'string', format: 'date-time', example: '2025-06-14T23:00:00.000000Z', nullable: true),
        new OA\Property(property: 'event_type', type: 'string', example: 'Wedding', nullable: true),
        new OA\Property(property: 'event_type_id', type: 'integer', example: 2, nullable: true),
        new OA\Property(property: 'eventable_type', type: 'string', enum: ['Bookings', 'BandEvents'], description: 'Whether this event originates from a booking or a legacy band event', example: 'Bookings'),
        new OA\Property(property: 'eventable_id', type: 'integer', description: 'ID of the parent booking or band event', example: 42),
        new OA\Property(property: 'venue_name', type: 'string', example: 'Grand Ballroom', nullable: true),
        new OA\Property(property: 'venue_address', type: 'string', example: '123 Main St, Nashville, TN 37201', nullable: true),
        new OA\Property(property: 'status', type: 'string', nullable: true, example: 'confirmed'),
        new OA\Property(property: 'price', type: 'number', format: 'float', nullable: true, example: 3500.00),
        new OA\Property(property: 'notes', type: 'string', nullable: true),
        new OA\Property(property: 'is_public', type: 'boolean', example: false),
    ],
)]
#[OA\Schema(
    schema: 'BookingWriteBody',
    properties: [
        new OA\Property(property: 'name', type: 'string', maxLength: 255, example: 'Smith Wedding Reception'),
        new OA\Property(property: 'event_type_id', type: 'integer', example: 2),
        new OA\Property(property: 'date', type: 'string', format: 'date', example: '2025-06-14'),
        new OA\Property(property: 'start_time', type: 'string', description: 'HH:MM 24-hour format', example: '18:00'),
        new OA\Property(property: 'end_time', type: 'string', description: 'HH:MM 24-hour format (update only; ignored on create)', example: '23:00'),
        new OA\Property(property: 'price', type: 'number', format: 'float', minimum: 0, example: 3500.00),
        new OA\Property(property: 'venue_name', type: 'string', nullable: true, example: 'Grand Ballroom'),
        new OA\Property(property: 'venue_address', type: 'string', nullable: true, example: '123 Main St, Nashville, TN 37201'),
        new OA\Property(property: 'contract_option', type: 'string', enum: ['default', 'none', 'external'], example: 'default'),
        new OA\Property(property: 'status', type: 'string', enum: ['draft', 'pending', 'confirmed', 'cancelled'], example: 'draft'),
        new OA\Property(property: 'notes', type: 'string', nullable: true),
    ],
)]
// ── Error schemas ─────────────────────────────────────────────────────────────
#[OA\Schema(
    schema: 'ErrorUnauthorized',
    properties: [
        new OA\Property(property: 'error', type: 'string', example: 'Unauthorized'),
        new OA\Property(property: 'message', type: 'string', example: 'API token is required'),
    ],
)]
#[OA\Schema(
    schema: 'ErrorForbidden',
    properties: [
        new OA\Property(property: 'error', type: 'string', example: 'Forbidden'),
        new OA\Property(property: 'message', type: 'string', example: 'This API token does not have permission to perform this action'),
        new OA\Property(property: 'required_permission', type: 'string', example: 'api:read-bookings'),
    ],
)]
#[OA\Schema(
    schema: 'ErrorNotFound',
    properties: [
        new OA\Property(property: 'error', type: 'string', example: 'Not Found'),
        new OA\Property(property: 'message', type: 'string', example: 'Booking not found or does not belong to your band'),
    ],
)]
#[OA\Schema(
    schema: 'ErrorValidation',
    properties: [
        new OA\Property(property: 'message', type: 'string', example: 'The given data was invalid.'),
        new OA\Property(
            property: 'errors',
            type: 'object',
            additionalProperties: new OA\AdditionalProperties(type: 'array', items: new OA\Items(type: 'string')),
            example: ['date' => ['The date field is required.']],
        ),
    ],
)]
class OpenApi
{
}
