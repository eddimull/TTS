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

#[Name('create_booking')]
#[Title('Create a booking')]
#[Description('Creates a booking for the band exactly as the TTS app does: the contract (with the band\'s initial terms), the event and its timeline (load in, soundcheck, quiet; ceremony and dances for weddings), the default roster and the event value (= price) are all generated automatically, so don\'t ask the user for them. The end time is start_time + duration hours. Status defaults to draft. Pass the client (e.g. the lead who emailed) as `contact` with their name, email and phone if known; it becomes the primary contact, reusing the band\'s existing contact with that email. Same rules as the developer API\'s POST /bookings; you become the booking\'s author. Confirm the details with the user before calling. Requires bookings:write.')]
#[IsDestructive(false)]
class CreateBooking extends BandTool
{
    use DescribesContacts;

    public function schema(JsonSchema $schema): array
    {
        return [
            'band_id' => $this->bandIdSchema($schema),
            'name' => $schema->string()->max(255)->description('Booking name, e.g. "Smith Wedding".')->required(),
            'event_type_id' => $schema->integer()->description('Event type id (from list_event_types).')->required(),
            'date' => $schema->string()->format('date')->description('Event date, YYYY-MM-DD; today or later.')->required(),
            'start_time' => $schema->string()->pattern('^\d{2}:\d{2}$')->description('Start time, 24-hour HH:MM.')->required(),
            'duration' => $schema->integer()->min(1)->max(24)->description('Length in whole hours (1-24).')->required(),
            'price' => $schema->number()->min(0)->description('Price in dollars, e.g. 4500 or 1250.50.')->required(),
            'contract_option' => $schema->string()->enum(['default', 'none', 'external'])->description('default = TTS generates the contract; none = no contract; external = contract handled elsewhere.')->required(),
            'venue_name' => $schema->string()->max(255)->description('Venue name.'),
            'venue_address' => $schema->string()->description('Venue address.'),
            'status' => $schema->string()->enum(['draft', 'pending', 'confirmed', 'cancelled'])->description('Defaults to draft.'),
            'notes' => $schema->string()->description('Notes.'),
            'contact' => $this->contactSchema($schema)->description('The client to attach, e.g. the lead who asked for the booking.'),
            'contacts' => $schema->array()->items($this->contactSchema($schema))->max(10)->description('Further contacts to attach (e.g. a planner), besides `contact`.'),
        ];
    }

    public function handle(Request $request, BandBookingsService $bookings): Response
    {
        $band = $this->band($request);
        if ($band instanceof Response) {
            return $band;
        }

        $validated = $request->validate(BandBookingsService::createRules());

        $booking = $bookings->create($band, $validated, authorId: $request->user()->id);

        return $this->json([
            'message' => 'Booking created successfully',
            'booking' => $bookings->format($booking),
        ]);
    }
}
