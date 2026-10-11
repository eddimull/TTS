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
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[Name('update_booking')]
#[Title('Update a booking')]
#[Description('Updates a booking. Send only the fields to change. date, start_time, end_time, venue_name and venue_address apply to the booking\'s first (earliest) event. A price change re-splits the event values. Changing event_type_id changes only the booking\'s type; as in the TTS app, the existing event\'s timeline is not regenerated. Same rules as the developer API\'s PATCH /bookings/{id}. Requires bookings:write.')]
#[IsDestructive(false)]
#[IsIdempotent]
class UpdateBooking extends BandTool
{
    /** Fields that may be explicitly cleared with null; other nulls mean "unchanged". */
    private const CLEARABLE = ['venue_name', 'venue_address', 'notes'];

    public function schema(JsonSchema $schema): array
    {
        return [
            'band_id' => $this->bandIdSchema($schema),
            'booking_id' => $schema->integer()->description('Booking id.')->required(),
            'name' => $schema->string()->max(255)->description('Booking name.'),
            'event_type_id' => $schema->integer()->description('Event type id (from list_event_types).'),
            'date' => $schema->string()->format('date')->description('First event\'s date, YYYY-MM-DD.'),
            'start_time' => $schema->string()->pattern('^\d{2}:\d{2}$')->description('First event\'s start time, 24-hour HH:MM.'),
            'end_time' => $schema->string()->pattern('^\d{2}:\d{2}$')->description('First event\'s end time, 24-hour HH:MM.'),
            'price' => $schema->number()->min(0)->description('Price in dollars.'),
            'contract_option' => $schema->string()->enum(['default', 'none', 'external'])->description('Contract handling.'),
            'venue_name' => $schema->string()->max(255)->nullable()->description('Venue name (null clears it).'),
            'venue_address' => $schema->string()->nullable()->description('Venue address (null clears it).'),
            'status' => $schema->string()->enum(['draft', 'pending', 'confirmed', 'cancelled'])->description('Booking status.'),
            'notes' => $schema->string()->nullable()->description('Notes (null clears them).'),
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

        $validated = collect($request->validate(BandBookingsService::updateRules()))
            ->reject(fn ($value, string $field) => $value === null && ! in_array($field, self::CLEARABLE, true))
            ->all();

        if ($validated === []) {
            return Response::error('Nothing to update: pass at least one field to change.');
        }

        $booking = $bookings->update($booking, $validated);

        return $this->json([
            'message' => 'Booking updated successfully',
            'booking' => $bookings->format($booking),
        ]);
    }
}
