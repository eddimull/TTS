<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\BandApi\BandEventsQuery;
use App\Services\BandApi\DateFilters;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class EventsController extends Controller
{
    #[OA\Get(
        path: '/events',
        operationId: 'listEvents',
        summary: 'List events',
        description: "Returns events for the authenticated band from **both** bookings and legacy band events (polymorphic). Results are ordered by date ascending, then time ascending.\n\n**Required permission:** `api:read-events`",
        security: [['BearerToken' => []]],
        tags: ['Events'],
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
                    new OA\Property(property: 'events', type: 'array', items: new OA\Items(ref: '#/components/schemas/Event')),
                    new OA\Property(property: 'total', type: 'integer', example: 8),
                    new OA\Property(property: 'filters', ref: '#/components/schemas/AppliedFilters'),
                ]),
            ),
            new OA\Response(response: 401, description: 'Unauthorized', content: new OA\JsonContent(ref: '#/components/schemas/ErrorUnauthorized')),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/ErrorForbidden')),
        ],
    )]
    public function index(Request $request, BandEventsQuery $eventsQuery)
    {
        $band = $request->input('authenticated_band');

        if (!$band) {
            return response()->json([
                'error' => 'Band not found',
                'message' => 'Unable to identify band from API token'
            ], 400);
        }

        $filters = $request->validate(DateFilters::rules());

        $events = $eventsQuery->forBand($band, $filters);

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
        if ($applied = DateFilters::applied($filters)) {
            $response['filters'] = $applied;
        }

        return response()->json($response);
    }
}
