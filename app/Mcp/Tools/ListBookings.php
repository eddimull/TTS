<?php

namespace App\Mcp\Tools;

use App\Services\BandApi\BandBookingsService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list_bookings')]
#[Title('List bookings')]
#[Description('Lists all of the band\'s bookings (newest first) with their events, contacts, payments and amounts paid/due. Same data as the developer API\'s GET /bookings. For a date or date range, prefer check_availability. Requires bookings:read.')]
#[IsReadOnly]
#[IsIdempotent]
class ListBookings extends BandTool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'band_id' => $this->bandIdSchema($schema),
        ];
    }

    public function handle(Request $request, BandBookingsService $bookings): Response
    {
        $band = $this->band($request);
        if ($band instanceof Response) {
            return $band;
        }

        $rows = $bookings->list($band);

        return $this->json([
            'band' => ['id' => $band->id, 'name' => $band->name],
            'bookings' => $rows,
            'total' => $rows->count(),
        ]);
    }
}
