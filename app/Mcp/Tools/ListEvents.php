<?php

namespace App\Mcp\Tools;

use App\Services\BandApi\BandEventsQuery;
use App\Services\BandApi\DateFilters;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list_events')]
#[Title('List events')]
#[Description('Lists the band\'s events (gigs from bookings plus legacy band events), ordered by date then start time, optionally for one date or a date range. Same data as the developer API\'s GET /events. Requires events:read.')]
#[IsReadOnly]
#[IsIdempotent]
class ListEvents extends BandTool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'band_id' => $this->bandIdSchema($schema),
            'date' => $schema->string()->format('date')->description('Exact date, YYYY-MM-DD.'),
            'from' => $schema->string()->format('date')->description('Range start, inclusive, YYYY-MM-DD.'),
            'to' => $schema->string()->format('date')->description('Range end, inclusive, YYYY-MM-DD.'),
            'before' => $schema->string()->format('date')->description('Only dates strictly before this, YYYY-MM-DD.'),
            'after' => $schema->string()->format('date')->description('Only dates strictly after this, YYYY-MM-DD.'),
        ];
    }

    public function handle(Request $request, BandEventsQuery $events): Response
    {
        $band = $this->band($request);
        if ($band instanceof Response) {
            return $band;
        }

        // Unlike a query string, tool arguments may carry explicit nulls: drop them.
        $filters = array_filter($request->validate(DateFilters::rules()), fn ($value) => $value !== null);
        $rows = $events->forBand($band, $filters);

        $result = [
            'band' => ['id' => $band->id, 'name' => $band->name],
            'events' => $rows,
            'total' => $rows->count(),
        ];

        if ($applied = DateFilters::applied($filters)) {
            $result['filters'] = $applied;
        }

        return $this->json($result);
    }
}
