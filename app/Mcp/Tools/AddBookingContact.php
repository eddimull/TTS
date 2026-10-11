<?php

namespace App\Mcp\Tools;

use App\Mcp\Tools\Concerns\DescribesContacts;
use App\Services\BandApi\BandBookingsService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[Name('add_booking_contact')]
#[Title('Add a contact to a booking')]
#[Description('Attaches a client contact (name, email, optional phone and role) to a booking. The band\'s existing contact with that email is reused, otherwise one is created. Adding a contact that is already attached updates its role and is_primary. is_primary defaults to true when the booking has no other primary contact. Same as the developer API\'s POST /bookings/{id}/contacts. Requires bookings:write.')]
#[IsDestructive(false)]
#[IsIdempotent]
class AddBookingContact extends BandTool
{
    use DescribesContacts;

    public function schema(JsonSchema $schema): array
    {
        return [
            'band_id' => $this->bandIdSchema($schema),
            'booking_id' => $schema->integer()->description('Booking id.')->required(),
            ...$this->contactProperties($schema, true),
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

        $validated = $request->validate(BandBookingsService::contactRules());

        $booking = $bookings->addContact($band, $booking, $validated);

        return $this->json([
            'message' => 'Contact added successfully',
            'booking' => $bookings->format($booking),
        ]);
    }
}
