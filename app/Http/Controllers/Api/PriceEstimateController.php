<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PriceEstimateRequest;
use App\Services\PriceEstimatorService;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

/**
 * Developer API (band API token) price estimate.
 *
 * Same validation (PriceEstimateRequest) and response body as the mobile
 * `mobile.price-estimate` endpoint. The band is always the token's band —
 * there is no user on these requests and no band in the URL.
 */
class PriceEstimateController extends Controller
{
    public function __construct(
        private PriceEstimatorService $estimator
    ) {}

    #[OA\Post(
        path: '/price-estimate',
        operationId: 'createPriceEstimate',
        summary: 'Estimate a booking price',
        description: "Returns a suggested price (with a low/high range, confidence, and an itemised breakdown) for a prospective booking, based on the band's booking history and pricing rules. Read-only: nothing is created or saved.\n\nIf `distance_miles` is omitted and `venue_address` is given, the distance from the band's address is looked up to calculate travel/lodging fees.\n\nWhen the band has no priced booking history and no manual base rate for the event type, `estimate`, `low`, `high` and `baseline` are `null` and `warnings` contains a `no_baseline` entry.\n\n**Required permission:** `api:read-pricing`",
        security: [['BearerToken' => []]],
        tags: ['Pricing'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['event_type_id', 'date'],
                properties: [
                    new OA\Property(property: 'event_type_id', type: 'integer', example: 1, description: 'Event type ID'),
                    new OA\Property(property: 'date', type: 'string', format: 'date', example: '2027-05-15', description: 'Event date (YYYY-MM-DD)'),
                    new OA\Property(property: 'start_time', type: 'string', nullable: true, example: '19:00', description: 'HH:MM or HH:MM:SS. Used with end_time to compute duration'),
                    new OA\Property(property: 'end_time', type: 'string', nullable: true, example: '23:00', description: 'HH:MM or HH:MM:SS'),
                    new OA\Property(property: 'duration_hours', type: 'number', nullable: true, example: 4, description: 'Used when start/end time are not both given (0.25-24). Defaults to the standard length for the event type'),
                    new OA\Property(property: 'venue_address', type: 'string', nullable: true, example: '123 Main St, Lafayette, LA', description: 'Used to look up the travel distance when distance_miles is omitted (max 500 chars)'),
                    new OA\Property(property: 'distance_miles', type: 'number', nullable: true, example: 85, description: 'One-way distance to the venue in miles (0-10000)'),
                    new OA\Property(property: 'add_ons', type: 'array', nullable: true, items: new OA\Items(type: 'string'), example: ['ceremony'], description: "Add-on keys from the band's pricing settings"),
                    new OA\Property(property: 'exclude_booking_id', type: 'integer', nullable: true, example: null, description: "Don't flag this booking's own date as a conflict"),
                ],
            ),
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Success',
                content: new OA\JsonContent(
                    example: [
                        'estimate' => 4250.0,
                        'low' => 3800.0,
                        'high' => 4700.0,
                        'confidence' => 'high',
                        'baseline_source' => 'history',
                        'sample_size' => 14,
                        'baseline' => 3900.0,
                        'duration_hours' => 4.0,
                        'distance_miles' => 85.0,
                        'breakdown' => [
                            ['key' => 'baseline', 'label' => 'Wedding baseline (14 past bookings)', 'type' => 'base', 'factor' => null, 'amount' => 3900.0],
                            ['key' => 'season', 'label' => 'May (peak season)', 'type' => 'multiplier', 'factor' => 1.1, 'amount' => 390.0],
                            ['key' => 'day_of_week', 'label' => 'Saturday', 'type' => 'multiplier', 'factor' => 1.0, 'amount' => 0.0],
                            ['key' => 'travel', 'label' => 'Travel (85 mi one-way)', 'type' => 'fee', 'factor' => null, 'amount' => 35.0],
                        ],
                        'warnings' => [
                            ['code' => 'date_conflict', 'message' => 'You already have a booking on May 15, 2027.'],
                        ],
                        'comparables' => [
                            ['booking_id' => 812, 'name' => 'Smith Wedding', 'date' => '2026-05-16', 'price' => 4000.0, 'venue_name' => 'The Grand Hall'],
                        ],
                    ],
                    properties: [
                        new OA\Property(property: 'estimate', type: 'number', nullable: true, description: 'Suggested price, rounded to the band\'s round_to step'),
                        new OA\Property(property: 'low', type: 'number', nullable: true),
                        new OA\Property(property: 'high', type: 'number', nullable: true),
                        new OA\Property(property: 'confidence', type: 'string', enum: ['low', 'medium', 'high']),
                        new OA\Property(property: 'baseline_source', type: 'string', enum: ['manual', 'history', 'band_average', 'none']),
                        new OA\Property(property: 'sample_size', type: 'integer'),
                        new OA\Property(property: 'baseline', type: 'number', nullable: true),
                        new OA\Property(property: 'duration_hours', type: 'number'),
                        new OA\Property(property: 'distance_miles', type: 'number', nullable: true),
                        new OA\Property(property: 'breakdown', type: 'array', items: new OA\Items(properties: [
                            new OA\Property(property: 'key', type: 'string', description: 'baseline, season, day_of_week, special_date, duration, short_notice, travel, lodging, or addon:<key>'),
                            new OA\Property(property: 'label', type: 'string'),
                            new OA\Property(property: 'type', type: 'string', enum: ['base', 'multiplier', 'fee']),
                            new OA\Property(property: 'factor', type: 'number', nullable: true),
                            new OA\Property(property: 'amount', type: 'number'),
                        ])),
                        new OA\Property(property: 'warnings', type: 'array', items: new OA\Items(properties: [
                            new OA\Property(property: 'code', type: 'string', description: 'date_conflict, low_sample, no_baseline, band_address_missing, distance_unavailable'),
                            new OA\Property(property: 'message', type: 'string'),
                        ])),
                        new OA\Property(property: 'comparables', type: 'array', items: new OA\Items(properties: [
                            new OA\Property(property: 'booking_id', type: 'integer'),
                            new OA\Property(property: 'name', type: 'string'),
                            new OA\Property(property: 'date', type: 'string', format: 'date'),
                            new OA\Property(property: 'price', type: 'number'),
                            new OA\Property(property: 'venue_name', type: 'string', nullable: true),
                        ])),
                    ],
                ),
            ),
            new OA\Response(response: 401, description: 'Unauthorized', content: new OA\JsonContent(ref: '#/components/schemas/ErrorUnauthorized')),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/ErrorForbidden')),
            new OA\Response(response: 422, description: 'Validation failed', content: new OA\JsonContent(ref: '#/components/schemas/ErrorValidation')),
        ],
    )]
    public function store(PriceEstimateRequest $request): JsonResponse
    {
        $band = $request->get('api_token')->band;

        return response()->json($this->estimator->estimate($band, $request->validated()), 200, [], JSON_PRESERVE_ZERO_FRACTION);
    }
}
