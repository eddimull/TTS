<?php

namespace App\Mcp\Tools;

use App\Services\BandApi\BandBookingsService;
use App\Services\BandApi\DateFilters;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('check_availability')]
#[Title('Check availability (booked dates)')]
#[Description('Lists the band\'s booked dates — bookings with their event dates, times and venues — optionally for one date or a date range. With `date`, the result also says whether the band is available that day (no non-cancelled booking). Same data as the developer API\'s GET /booked-dates. Requires bookings:read.')]
#[IsReadOnly]
#[IsIdempotent]
class CheckAvailability extends BandTool
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

    public function handle(Request $request, BandBookingsService $bookings): Response
    {
        $band = $this->band($request);
        if ($band instanceof Response) {
            return $band;
        }

        // Unlike a query string, tool arguments may carry explicit nulls: drop them.
        $filters = array_filter($request->validate(DateFilters::rules()), fn ($value) => $value !== null);
        $rows = $bookings->bookedDates($band, $filters);

        $result = [
            'band' => ['id' => $band->id, 'name' => $band->name],
            'bookings' => $rows,
            'total' => $rows->count(),
        ];

        if ($applied = DateFilters::applied($filters)) {
            $result['filters'] = $applied;
        }

        if (! empty($filters['date'])) {
            $result['available'] = $rows->every(fn (array $row) => $row['status'] === 'cancelled');
        }

        return $this->json($result);
    }
}
