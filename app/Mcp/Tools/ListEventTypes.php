<?php

namespace App\Mcp\Tools;

use App\Models\EventTypes;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list_event_types')]
#[Title('List event types')]
#[Description('Lists the event types (Wedding, Bar Gig, ...) and their ids. Use an event_type_id from here when creating or updating a booking or estimating a price.')]
#[IsReadOnly]
#[IsIdempotent]
class ListEventTypes extends Tool
{
    public function handle(Request $request): Response
    {
        // Same list and shape as the mobile app's /event-types endpoint.
        return Response::json([
            'event_types' => EventTypes::orderBy('id')->get(['id', 'name']),
        ]);
    }
}
