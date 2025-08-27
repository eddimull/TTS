<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use App\Models\BandCalendars;
use App\Models\CalendarAccess;
use Google\Service\Calendar\Calendar;
use App\Services\GoogleCalendarService;
use Spatie\GoogleCalendar\GoogleCalendar;
use App\Formatters\CalendarAccessFormatter;
use App\Rules\PartOfBand;

class CalendarAccessController extends Controller
{
    protected $googleCalendarService;
    public function __construct(GoogleCalendarService $googleCalendarService)
    {
        $this->googleCalendarService = $googleCalendarService;
    }

    public function create(BandCalendars $calendar, Request $request)
    {
        $band = $calendar->band;
        $request->validate([
            'user_id' => ['required', 'exists:users,id', new PartOfBand($band)],
            'role' => 'required|in:reader,writer,owner',
        ]);
        $user = User::find($request->input('user_id'));
        $AclRule = CalendarAccessFormatter::formatACLRule($user->email, $request->input('role'));
        // Use the Google Calendar service to create a new calendar event
        $this->googleCalendarService->addAccess($calendar->googleCalendar, $AclRule);

        CalendarAccess::updateOrCreate([
            'band_calendar_id' => $calendar->id,
            'user_id' => $user->id,
            'role' => $request->input('role'),
        ]);

        return redirect()->back()->with('successMessage', 'Access granted successfully');
    }

    public function destroy(BandCalendars $calendar, User $user)
    {
        
        $access = CalendarAccess::where('band_calendar_id', $calendar->id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $googleCalendar = $calendar->googleCalendar;

        $acl = $this->googleCalendarService->findAccess($googleCalendar, $user->email);
        // Use the Google Calendar service to remove access
        $this->googleCalendarService->revokeAccess($googleCalendar, $acl);

        $access->delete();

        return redirect()->back()->with('successMessage', 'Access revoked successfully');
    }

}
