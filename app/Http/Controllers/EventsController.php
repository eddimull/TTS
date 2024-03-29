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

class EventsController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        // $events = DB::select(DB::raw('SELECT band_events.*,ET.name AS event_type 
        //                         FROM band_events 
        //                         JOIN band_owners BO ON BO.band_id = band_events.band_id 
        //                         JOIN event_types ET ON ET.id = band_events.event_type_id 
        //                         WHERE BO.user_id = ? AND band_events.deleted_at IS NULL 
        //                         ORDER BY event_time ASC'),[Auth::id()]);
        // dd($events);
        return Inertia::render('Events/Index', [
            'events' => Auth::user()->events
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
        $event->band = $event->band;
        $event->event_type_name = $event->event_type;
        compact($event->state);
        compact($event->colorway);


        // dd($event->event_type_name);
        // return Inertia::render('Events/Advance',[
        //     'event'=>$event            
        // ]);
        return View('advance.advance', ['event' => $event]);
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

        // dd($request);
        $strtotime = strtotime($request->event_time);
        $formattedTime = date('Y-m-d', $strtotime);
        // dd($request->end_time);
        $event = BandEvents::create([
            'band_id' => $request->band_id,
            'event_name' => $request->event_name,
            'venue_name' => $request->venue_name,
            'first_dance' => $request->first_dance,
            'father_daughter' => $request->father_daughter,
            'mother_groom' => $request->mother_groom,
            'money_dance' => $request->money_dance,
            'bouquet_garter' => $request->bouquet_garter,
            'address_street' => $request->address_street,
            'production_needed' => $request->production_needed,
            'backline_provided' => $request->backline_provided,
            'zip' => $request->zip,
            'notes' => $request->notes,
            'event_time' => date('Y-m-d H:i:s', strtotime($request->event_time)),
            'band_loadin_time' =>  date('Y-m-d H:i:s', strtotime($request->band_loadin_time)),
            'rhythm_loadin_time' => date('Y-m-d H:i:s', strtotime($request->rhythm_loadin_time)),
            'production_loadin_time' => date('Y-m-d H:i:s', strtotime($request->production_loadin_time)),
            'pay' => $request->pay,
            'depositReceived' => $request->depositReceived,
            'event_key' => $request->event_key,
            'created_at' => $request->created_at,
            'updated_at' => $request->updated_at,
            'public' => $request->public,
            'event_type_id' => $request->event_type_id,
            'lodging' => $request->lodging,
            'state_id' => $request->state_id,
            'city' => $request->city,
            'colorway_id' => $request->colorway_id,
            'colorway_text' => $request->colorway_text,
            'quiet_time' => date('Y-m-d H:i:s', strtotime($request->quiet_time)),
            'end_time' => date('Y-m-d H:i:s', strtotime($request->end_time)),
            'ceremony_time' => date('Y-m-d H:i:s', strtotime($request->ceremony_time)),
            'outside' => $request->outside,
            'second_line' => $request->second_line,
            'onsite' => $request->onsite,
            'event_key' => Str::uuid()
        ]);


        $band = Bands::find($event->band_id);
        $calService = new CalendarService($band);
        $calService->writeEventToCalendar($event);

        $editor = Auth::user();
        compact($band->owners);
        foreach ($band->owners as $owner) {
            $user = User::find($owner->user_id);
            $user->notify(new TTSNotification([
                'text' => $editor->name . ' added ' . $event->event_name,
                'route' => 'events.advance',
                'routeParams' => $event->event_key,
                'url' => '/events/' . $event->event_key . '/advance'
            ]));
        }
        compact($band->members);

        foreach ($band->members as $member) {
            $user = User::find($member->user_id);
            $user->notify(new TTSNotification([
                'text' => $editor->name . ' added ' . $event->event_name,
                'route' => 'events.advance',
                'routeParams' => $event->event_key,
                'url' => '/events/' . $event->event_key . '/advance'
            ]));
        }
        return redirect()->route('events')->with('successMessage', 'Event was successfully added');
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
        $request->validate([
            'event_name' => 'required'
        ]);
        // dd(date('Y-m-d',strtotime($request->event_time) . ' ' . date('H:i:s',strtotime($request->end_time))));
        // dd($request->end_time);
        $strtotime = strtotime($request->event_time);
        $formattedTime = date('Y-m-d', $strtotime);
        $event = BandEvents::where('event_key', $request->event_key)->first();

        foreach ([
            'event_time',
            'band_loadin_time',
            'end_time',
            'rhythm_loadin_time',
            'production_loadin_time',
            'ceremony_time',
            'quiet_time'
        ] as $time) {
            $request[$time] = date('Y-m-d H:i:s', strtotime($request->$time));
        }

        $event->update($request->all());


        $band = Bands::find($event->band_id);
        if ($band->calendar_id !== '' && $band->calendar_id !== null) {

            Config::set('google-calendar.service_account_credentials_json', storage_path('/app/google-calendar/service-account-credentials.json'));
            Config::set('google-calendar.calendar_id', $band->calendar_id);

            // dd(Carbon::parse($event->event_time));

            if ($event->google_calendar_event_id !== null) {
                $calendarEvent = CalendarEvent::find($event->google_calendar_event_id);
            } else {
                $calendarEvent = new CalendarEvent;
            }
            $calendarEvent->name = $event->event_name;

            $startTime = Carbon::parse($event->event_time);
            $endDateTimeFixed = date('Y-m-d', strtotime($event->event_time)) . ' ' . date('H:i:s', strtotime($event->end_time));
            if ($endDateTimeFixed < $startTime) //when events end after midnight
            {
                $endDateTimeFixed = date('Y-m-d', strtotime($event->event_time . ' +1 day')) . ' ' . date('H:i:s', strtotime($event->end_time));
            }
            $endTime = Carbon::parse($endDateTimeFixed);
            $calendarEvent->startDateTime = $startTime;
            $calendarEvent->endDateTime = $endTime;
            $calendarEvent->description = 'http://tts.band/events/' . $event->event_key . '/advance';
            $google_id = $calendarEvent->save();
            $event->google_calendar_event_id = $google_id->id;
            $event->save();
        }
        $editor = Auth::user();
        compact($band->owners);
        foreach ($band->owners as $owner) {
            $user = User::find($owner->user_id);
            $user->notify(new TTSNotification([
                'text' => $editor->name . ' updated ' . $event->event_name,
                'route' => 'events.advance',
                'routeParams' => $event->event_key,
                'url' => '/events/' . $event->event_key . '/advance'
            ]));
        }

        compact($band->members);
        foreach ($band->members as $member) {
            $user = User::find($member->user_id);
            $user->notify(new TTSNotification([
                'text' => $editor->name . ' updated ' . $event->event_name,
                'route' => 'events.advance',
                'routeParams' => $event->event_key,
                'url' => '/events/' . $event->event_key . '/advance'
            ]));
        }

        return redirect()->route('events')->with('successMessage', $request->event_name . ' was successfully updated');
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
        //
        $event = BandEvents::where('event_key', $key)->first();
        $band = $event->band;

        if ($band->calendar_id !== '' && $band->calendar_id !== null && $event->google_calendar_event_id !== null) {

            Config::set('google-calendar.service_account_credentials_json', storage_path('/app/google-calendar/service-account-credentials.json'));
            Config::set('google-calendar.calendar_id', $band->calendar_id);
            $calendarEvent = CalendarEvent::find($event->google_calendar_event_id);
            $calendarEvent->delete();
        }

        $editor = Auth::user();
        compact($band->owners);
        foreach ($band->owners as $owner) {
            $user = User::find($owner->user_id);
            $user->notify(new TTSNotification([
                'text' => $editor->name . ' deleted ' . $event->event_name,
                'route' => 'events',
                'routeParams' => null,
                'emailHeader' => 'An event was deleted',
                'url' => '/events/'
            ]));
        }

        $event->delete();
        return redirect()->route('events')->with('successMessage', $event->event_name . ' was successfully deleted');
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
