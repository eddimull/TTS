<?php

namespace App\Mcp\Tools;

use App\Http\Requests\PriceEstimateRequest;
use App\Services\PriceEstimatorService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('estimate_price')]
#[Title('Estimate a booking price')]
#[Description('Suggests a price in dollars (with low/high range, confidence and an itemised breakdown) for a prospective booking, from the band\'s booking history and pricing settings. Read-only: nothing is saved. If distance_miles is omitted and venue_address is given, travel distance from the band\'s address is looked up. Band owners only. Same input and output as the developer API\'s POST /price-estimate. Requires pricing:read.')]
#[IsReadOnly]
#[IsIdempotent]
class EstimatePrice extends BandTool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'band_id' => $this->bandIdSchema($schema),
            'event_type_id' => $schema->integer()->description('Event type id (from list_event_types).')->required(),
            'date' => $schema->string()->format('date')->description('Event date, YYYY-MM-DD.')->required(),
            'start_time' => $schema->string()->description('Start time, HH:MM (24-hour). With end_time, sets the duration.'),
            'end_time' => $schema->string()->description('End time, HH:MM (24-hour).'),
            'duration_hours' => $schema->number()->min(0.25)->max(24)->description('Length in hours, used when start/end times are not both given. Defaults to the event type\'s standard length.'),
            'venue_address' => $schema->string()->max(500)->description('Venue address, used to look up travel distance.'),
            'distance_miles' => $schema->number()->min(0)->max(10000)->description('One-way distance to the venue in miles.'),
            'add_ons' => $schema->array()->items($schema->string())->description('Add-on keys from the band\'s pricing settings, e.g. ["ceremony"].'),
            'exclude_booking_id' => $schema->integer()->description('When re-pricing an existing booking, its id, so its own date is not flagged as a conflict.'),
        ];
    }

    public function handle(Request $request, PriceEstimatorService $estimator): Response
    {
        $band = $this->band($request);
        if ($band instanceof Response) {
            return $band;
        }

        $validated = $request->validate((new PriceEstimateRequest)->rules());

        return $this->json($estimator->estimate($band, $validated));
    }
}
