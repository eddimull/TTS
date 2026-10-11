<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\CheckAvailability;
use App\Mcp\Tools\CreateBooking;
use App\Mcp\Tools\DeleteBooking;
use App\Mcp\Tools\EstimatePrice;
use App\Mcp\Tools\GetBooking;
use App\Mcp\Tools\ListBands;
use App\Mcp\Tools\ListBookings;
use App\Mcp\Tools\ListEvents;
use App\Mcp\Tools\ListEventTypes;
use App\Mcp\Tools\UpdateBooking;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

/**
 * Remote MCP server for TTS Bandmate, mounted at /mcp (routes/ai.php) behind
 * Passport OAuth. Exposes the band developer API's capabilities, scoped to
 * the signed-in user's bands and permissions.
 */
#[Name('TTS Bandmate')]
#[Version('1.0.0')]
#[Instructions(<<<'MARKDOWN'
    TTS Bandmate manages bookings, gigs and pricing for bands. You act as the signed-in user, within the bands they own or are a member of.

    How to use these tools:
    - Always call `list_bands` first. It returns each band's `id`, the user's `role` (owner/member) and the `tools` they can use in that band with this connection. Every other band tool needs a `band_id` from it; if the user has several bands and it is unclear which one they mean, ask.
    - Use `list_event_types` to turn an event type name ("wedding") into the `event_type_id` that create_booking, update_booking and estimate_price need.
    - Money is always in US dollars as plain numbers (4500 or 1250.50) — never cents. Prices in results are dollar strings like "4500.00".
    - Dates are YYYY-MM-DD and times are 24-hour HH:MM, in the band's local time.
    - "Is the band free on <date>?" → `check_availability` with `date`; it returns `available`. For a range use `from`/`to` (inclusive) or `after`/`before` (exclusive).
    - `estimate_price` is for band owners only; members get an error. It never saves anything. Present the estimate with its range and the breakdown's main factors, and mention any warnings (e.g. a date conflict).
    - Before `create_booking`, `update_booking` or `delete_booking`, confirm the details with the user. `delete_booking` is permanent; prefer `update_booking` with status "cancelled" unless the user explicitly wants the booking deleted.
    - An error saying a scope was not granted means the user must reconnect the connector and approve that access; a permission error means a band owner must grant it in TTS Bandmate. Don't retry those.
    MARKDOWN)]
class BandmateServer extends Server
{
    protected array $tools = [
        ListBands::class,
        ListEventTypes::class,
        CheckAvailability::class,
        ListEvents::class,
        ListBookings::class,
        GetBooking::class,
        CreateBooking::class,
        UpdateBooking::class,
        DeleteBooking::class,
        EstimatePrice::class,
    ];

    protected array $resources = [];

    protected array $prompts = [];
}
