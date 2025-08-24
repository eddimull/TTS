<?php

namespace App\Http\Controllers;

use App\Models\Bands;
use App\Jobs\SyncCalendar;
use Illuminate\Http\Request;
use App\Models\BandCalendars;
use App\Formatters\CalendarFormatter;
use App\Services\GoogleCalendarService;
use Spatie\GoogleCalendar\GoogleCalendar;

class BandCalendarController extends Controller
{
    protected $googleCalendarService;

    public function __construct(GoogleCalendarService $googleCalendarService)
    {
        $this->googleCalendarService = $googleCalendarService;
    }

    public function create(Bands $band, Request $request)
    {   
        $validated = $request->validate([
            'type' => 'required|in:booking,event,public',
        ]);
        $formattedCalendar = CalendarFormatter::formatCalendar($band, $validated['type']);
        $googleCalendar = $this->googleCalendarService->createCalendar($formattedCalendar);

        BandCalendars::create([
            'band_id' => $band->id,
            'calendar_id' => $googleCalendar->id,
            'type' => $validated['type'],
        ]);

        return redirect()->back()->with('successMessage', 'Calendar created successfully');
    }

    public function syncCalendar(BandCalendars $calendar)
    {
        SyncCalendar::dispatch($calendar);
        return redirect()->back()->with(['successMessage' => 'Calendar synced successfully. Please wait a few minutes for events to populate.']);
    }

}
