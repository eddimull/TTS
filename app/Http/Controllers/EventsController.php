<?php

namespace App\Http\Controllers;

use App\Models\EventTypes;
use App\Models\State;
use App\Models\Bands;
use App\Models\BandEvents;
use App\Models\BandOwners;
use App\Models\EventContacts;
use App\Models\Proposals;
use Carbon\Carbon;
use PDF;
use Doctrine\DBAL\Events;
use Illuminate\Support\Facades\DB;
use Faker\Provider\Uuid;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use Spatie\GoogleCalendar\Event as CalendarEvent;
use App\Notifications\EventAdded;
use App\Notifications\EventUpdated;
use App\Models\User;
use App\Notifications\TTSNotification;
use App\Services\CalendarService;
use Doctrine\DBAL\Schema\View;
use Illuminate\Support\Facades\Http;
use App\Http\Requests\EventRequest;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class EventsController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $afterDate = Carbon::now()->subMonth(1);
        $events = Auth::user()->getEventsAttribute($afterDate);
        return Inertia::render('Events/Index', [
            'events' => $events
        ]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $eventTypes = EventTypes::orderBy('name')->get();
        $states = State::where('country_id', 231)->get();
        $bands = Bands::select('bands.*')->join('band_owners', 'bands.id', '=', 'band_owners.band_id')->where('user_id', Auth::id())->get();
        foreach ($bands as $band) {
            $colors = $band->colorways;
            $band->colors = $colors;
        }
        return Inertia::render('Events/Create', [
            'eventTypes' => $eventTypes,
            'states' => $states,
            'bands' => $bands
        ]);
    }
    public function advance($key)
    {
        $event = BandEvents::where('event_key', $key)->first();

        if (!$event) {
            abort(404, 'Event not found');
        }

        // Load relationships if they're not already loaded
        $event->load(['band', 'state', 'colorway']);

        // Add event_type_name to the event object
        $event->event_type_name = $event->event_type;

        // Uncomment the following line if you want to use Inertia instead of the default View
        // return Inertia::render('Events/Advance', ['event' => $event]);

        return view('advance.advance', ['event' => $event]);
    }
    public function createPDF($id)
    {
        $event = BandEvents::where('id', $id)->first();
        $event->band = $event->band;
        $event->event_type_name = $event->event_type;


        // dd($event->event_type_name);
        return view('events', ['event' => $event]);
    }

    public function downloadPDF($id)
    {
        $event = BandEvents::where('id', $id)->first();
        $event->band = $event->band;
        $event->event_type_name = $event->event_type;

        $pdf = PDF::loadView('events', ['event' => $event]);

        return $pdf->download($event->band->name . ' - ' . $event->event_name . '.pdf');


        // dd($event->event_type_name);

    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(EventRequest $request)
    {
        $eventData = $this->prepareEventData($request);
        $event = BandEvents::create($eventData);

        $band = Bands::findOrFail($event->band_id);
        $this->writeEventToCalendar($band, $event);
        $this->notifyBandMembers($band, $event);

        return redirect()->route('events')->with('successMessage', 'Event was successfully added');
    }

    private function prepareEventData(EventRequest $request): array
    {
        $dateFields = ['event_time', 'band_loadin_time', 'rhythm_loadin_time', 'production_loadin_time', 'quiet_time', 'end_time', 'ceremony_time'];

        $eventData = $request->except(['created_at', 'updated_at']);

        foreach ($dateFields as $field) {
            if (isset($eventData[$field])) {
                $eventData[$field] = date('Y-m-d H:i:s', strtotime($eventData[$field]));
            }
        }

        $eventData['event_key'] = Str::uuid();

        return $eventData;
    }

    private function writeEventToCalendar(Bands $band, BandEvents $event): void
    {
        $calService = new CalendarService($band);
        $calService->writeEventToCalendar($event);
    }

    private function notifyBandMembers(Bands $band, BandEvents $event): void
    {
        $editor = Auth::user();
        $notificationData = [
            'text' => $editor->name . ' added ' . $event->event_name,
            'route' => 'events.advance',
            'routeParams' => $event->event_key,
            'url' => '/events/' . $event->event_key . '/advance'
        ];

        $band->load('owners.user', 'members.user');

        $usersToNotify = $band->owners->pluck('user')->merge($band->members->pluck('user'));

        Notification::send($usersToNotify, new TTSNotification($notificationData));
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($key)
    {
        $eventTypes = EventTypes::orderBy('name')->get();
        $event = BandEvents::where('event_key', $key)->first();
        $states = State::where('country_id', 231)->get();
        $bands = Bands::select('bands.*')->join('band_owners', 'bands.id', '=', 'band_owners.band_id')->where('user_id', Auth::id())->get();
        foreach ($bands as $band) {
            $colors = $band->colorways;
            $band->colors = $colors;
        }

        return Inertia::render('Events/Edit', [
            'event' => $event,
            'eventTypes' => $eventTypes,
            'states' => $states,
            'bands' => $bands
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $request->validate(['event_name' => 'required']);

        $event = BandEvents::where('event_key', $request->event_key)->firstOrFail();

        $this->updateEventTimes($request);
        $event->update($request->all());

        $band = Bands::findOrFail($event->band_id);

        if ($band->calendar_id) {
            $this->updateGoogleCalendarEvent($event, $band);
        }

        $this->notifyBandMembers($band, $event, 'updated');

        return redirect()->route('events')->with('successMessage', "{$request->event_name} was successfully updated");
    }

    private function updateEventTimes(Request &$request): void
    {
        $timeFields = [
            'event_time',
            'band_loadin_time',
            'end_time',
            'rhythm_loadin_time',
            'production_loadin_time',
            'ceremony_time',
            'quiet_time'
        ];

        foreach ($timeFields as $field) {
            if ($request->has($field)) {
                $request[$field] = Carbon::parse($request->$field)->format('Y-m-d H:i:s');
            }
        }
    }

    private function updateGoogleCalendarEvent(BandEvents $event, Bands $band): void
    {
        Config::set('google-calendar.service_account_credentials_json', storage_path('/app/google-calendar/service-account-credentials.json'));
        Config::set('google-calendar.calendar_id', $band->calendar_id);

        $calendarEvent = $event->google_calendar_event_id
            ? CalendarEvent::find($event->google_calendar_event_id)
            : new CalendarEvent;

        $startTime = Carbon::parse($event->event_time);
        $endTime = $this->calculateEndTime($event);

        $calendarEvent->name = $event->event_name;
        $calendarEvent->startDateTime = $startTime;
        $calendarEvent->endDateTime = $endTime;
        $calendarEvent->description = "http://tts.band/events/{$event->event_key}/advance";

        $google_id = $calendarEvent->save();
        $event->google_calendar_event_id = $google_id->id;
        $event->save();
    }

    private function calculateEndTime(BandEvents $event): Carbon
    {
        $startDate = Carbon::parse($event->event_time)->format('Y-m-d');
        $endTime = Carbon::parse($event->end_time)->format('H:i:s');
        $endDateTime = Carbon::parse("$startDate $endTime");

        if ($endDateTime < Carbon::parse($event->event_time)) {
            $endDateTime->addDay();
        }

        return $endDateTime;
    }

    public function getGoogleMapsImage(BandEvents $event)
    {
        return Http::get("https://maps.googleapis.com/maps/api/staticmap?api=1&center=" . urlencode($event->venue_name . ' ' . $event->address_street . ' ' . $event->city . ', ' . $event->state->state_name . ' ' . $event->zip) . '&size=400x400&key=' . $_ENV['GOOGLE_STATIC_MAP_KEY']);
    }
    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($key)
    {
        $event = BandEvents::where('event_key', $key)->firstOrFail();
        $band = $event->band;

        $this->deleteGoogleCalendarEvent($event, $band);
        $this->notifyBandMembers($band, $event);

        $eventName = $event->event_name;
        $event->delete();

        return redirect()->route('events')->with('successMessage', "{$eventName} was successfully deleted");
    }

    private function deleteGoogleCalendarEvent(BandEvents $event, Bands $band): void
    {
        if ($band->calendar_id && $event->google_calendar_event_id) {
            Config::set('google-calendar.service_account_credentials_json', storage_path('/app/google-calendar/service-account-credentials.json'));
            Config::set('google-calendar.calendar_id', $band->calendar_id);

            $calendarEvent = CalendarEvent::find($event->google_calendar_event_id);
            if ($calendarEvent) {
                $calendarEvent->delete();
            }
        }
    }


    public function createContact(Request $request, BandEvents $event)
    {
        $request->validate([
            'name' => 'required',
            'email' => 'required|email:rfc,dns'
        ]);

        EventContacts::create([
            'event_id' => $event->id,
            'email' => $request->email,
            'phonenumber' => $request->phonenumber,
            'name' => $request->name
        ]);
        $event->refresh();
        return response()->json(['successMessage' => 'Added ' . $request->name . ' as contact', 'contacts' => $event->event_contacts]);
    }


    public function editContact(Request $request, EventContacts $contact)
    {
        $request->validate([
            'name' => 'required',
            'email' => 'required|email:rfc,dns'
        ]);
        $contact->email = $request->email;
        $contact->name = $request->name;
        $contact->phonenumber = $request->phonenumber;
        $contact->save();
        return back()->with('successMessage', 'Updated ' . $contact->name);
    }

    public function deleteContact(EventContacts $contact)
    {
        $contact->delete();
        return back()->with('successMessage', 'Removed Contact');
    }
}
