<?php

namespace App\Http\Controllers;

use App\Models\EventTypes;

class EventTypeController extends Controller
{
    public function getAllEventTypes()
    {
        $eventTypes = EventTypes::all();
        return response()->json($eventTypes);
    }
}
