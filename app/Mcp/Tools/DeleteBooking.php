<?php

namespace App\Mcp\Tools;

use App\Services\BandApi\BandBookingsService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[Name('delete_booking')]
#[Title('Delete a booking')]
#[Description('Permanently deletes a booking and cannot be undone — same as the developer API\'s DELETE /bookings/{id}. Always confirm with the user (name and date) before calling. To keep the record, use update_booking with status "cancelled" instead. Requires bookings:write.')]
#[IsDestructive]
class DeleteBooking extends BandTool
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

        $booking = $bookings->find($band, $request->get('booking_id'));

        if (! $booking) {
            return Response::error("Booking {$request->get('booking_id')} not found in {$band->name}.");
        }

        $summary = ['id' => $booking->id, 'name' => $booking->name];

        $bookings->delete($booking);

        return $this->json([
            'message' => 'Booking deleted successfully',
            'booking' => $summary,
        ]);
    }
}
