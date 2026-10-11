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

#[Name('get_booking')]
#[Title('Get a booking')]
#[Description('Gets one of the band\'s bookings by id, with its events, contacts, payments and amounts paid/due. Same data as the developer API\'s GET /bookings/{id}. Requires bookings:read.')]
#[IsReadOnly]
#[IsIdempotent]
class GetBooking extends BandTool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'band_id' => $this->bandIdSchema($schema),
            'booking_id' => $schema->integer()->description('Booking id.')->required(),
        ];
    }

    public function handle(Request $request, BandBookingsService $bookings): Response
    {
        $band = $this->band($request);
        if ($band instanceof Response) {
            return $band;
        }

        $request->validate(['booking_id' => 'required|integer']);

        $booking = $bookings->find($band, $request->get('booking_id'), withRelations: true);

        if (! $booking) {
            return Response::error("Booking {$request->get('booking_id')} not found in {$band->name}.");
        }

        return $this->json(['booking' => $bookings->format($booking)]);
    }
}
