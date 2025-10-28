<?php

namespace App\Http\Controllers;

use PDF;
use Carbon\Carbon;
use Inertia\Inertia;
use App\Models\Bands;
use App\Models\State;
use App\Models\Events;
use App\Models\BandEvents;
use App\Models\EventTypes;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Models\EventContacts;
use App\Http\Requests\EventRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use App\Notifications\TTSNotification;
use Event;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Notification;
use Spatie\GoogleCalendar\Event as CalendarEvent;

class EventsController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $includeAll = $request->boolean('include_all', false);
        
        if ($includeAll) {
            // Get all events without date filtering
            $events = Auth::user()->getEventsAttribute();
        } else {
            // Get events from 1 month ago (existing behavior)
            $afterDate = Carbon::now()->subMonth(1);
            $events = Auth::user()->getEventsAttribute($afterDate);
        }
        
        return Inertia::render('Events/Index', [
            'events' => $events,
            'includeAll' => $includeAll
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
        foreach ($bands as $band)
        {
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
        $event = Events::where('key', $key)->first();

        if (!$event)
        {
            abort(404, 'Event not found');
        }

        $event->band = $event->eventable->band;

        $event->type = $event->type;
        // Add event_type_name to the event object
        $event->event_type_name = $event->eventType;

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
        // $this->writeEventToCalendar($band, $event);
        // $this->notifyBandMembers($band, $event);

        return redirect()->route('events')->with('successMessage', 'Event was successfully added');
    }

    private function prepareEventData(EventRequest $request): array
    {
        $dateFields = ['event_time', 'band_loadin_time', 'rhythm_loadin_time', 'production_loadin_time', 'quiet_time', 'end_time', 'ceremony_time'];

        $eventData = $request->except(['created_at', 'updated_at']);

        foreach ($dateFields as $field)
        {
            if (isset($eventData[$field]))
            {
                $eventData[$field] = date('Y-m-d H:i:s', strtotime($eventData[$field]));
            }
        }

        $eventData['event_key'] = Str::uuid();

        return $eventData;
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
        $event = Events::where('key', $key)->first();

        return redirect()->route('Booking Events', [
            'band' => $event->eventable->band->id, 
            'booking' => $event->eventable->id,
            'edit' => $key  // Pass the event key to auto-open editor
        ]);

        $eventTypes = EventTypes::orderBy('name')->get();
        $event = BandEvents::where('event_key', $key)->first();
        $states = State::where('country_id', 231)->get();
        $bands = Bands::select('bands.*')->join('band_owners', 'bands.id', '=', 'band_owners.band_id')->where('user_id', Auth::id())->get();
        foreach ($bands as $band)
        {
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

        // if ($band->calendar_id)
        // {
        //     $this->updateGoogleCalendarEvent($event, $band);
        // }

        // $this->notifyBandMembers($band, $event, 'updated');

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

        foreach ($timeFields as $field)
        {
            if ($request->has($field))
            {
                $request[$field] = Carbon::parse($request->$field)->format('Y-m-d H:i:00');
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

        if ($endDateTime < Carbon::parse($event->event_time))
        {
            $endDateTime->addDay();
        }

        return $endDateTime;
    }

    public function getGoogleMapsImage(Events $event)
    {
        $venue_name = $event->eventable->venue_name;
        $venue_address = $event->eventable->venue_address;
        $location = urlencode($venue_name . ' ' . $venue_address);

        $url = "https://maps.googleapis.com/maps/api/staticmap?"
            . "center={$location}"
            . "&zoom=17"
            . "&size=400x400"
            . "&markers=color:red%7C{$location}"
            . "&key=" . config('googlemaps.key');

        return Http::get($url);
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
        if ($band->calendar_id && $event->google_calendar_event_id)
        {
            Config::set('google-calendar.service_account_credentials_json', storage_path('/app/google-calendar/service-account-credentials.json'));
            Config::set('google-calendar.calendar_id', $band->calendar_id);

            $calendarEvent = CalendarEvent::find($event->google_calendar_event_id);
            if ($calendarEvent)
            {
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

    /**
     * Display the activity history for an event
     *
     * @param  string  $key
     * @return \Inertia\Response
     */
    public function history($key)
    {
        $event = Events::where('key', $key)->firstOrFail();
        
        $activities = $this->getFormattedActivities($event);
        
        // Load event with necessary relationships
        $event->load('eventable.band', 'type');
        
        return Inertia::render('Events/History', [
            'event' => [
                'id' => $event->id,
                'key' => $event->key,
                'title' => $event->title,
                'date' => $event->date->format('Y-m-d'),
                'time' => $event->time,
                'band_name' => $event->eventable->band->name ?? 'Unknown',
                'event_type' => $event->type->name ?? 'Unknown',
            ],
            'activities' => $activities,
        ]);
    }

    /**
     * Get activity history for an event (JSON API endpoint)
     *
     * @param  string  $key
     * @return \Illuminate\Http\JsonResponse
     */
    public function historyJson($key)
    {
        $event = Events::where('key', $key)->firstOrFail();
        
        $activities = $this->getFormattedActivities($event);
        
        return response()->json([
            'activities' => $activities,
        ]);
    }

    /**
     * Get formatted activities for an event
     *
     * @param  Events  $event
     * @return \Illuminate\Support\Collection
     */
    private function getFormattedActivities(Events $event)
    {
        return $event->activities()
            ->with('causer')
            ->latest()
            ->get()
            ->map(function ($activity) {
                $changes = $activity->changes();
                
                // Format changes for better display
                $formattedChanges = [];
                if (isset($changes['attributes'])) {
                    foreach ($changes['attributes'] as $key => $newValue) {
                        $oldValue = $changes['old'][$key] ?? null;
                        
                        // Format field name for display
                        $fieldName = $this->formatFieldName($key);
                        
                        // Format values for display
                        $formattedChanges[] = [
                            'field' => $fieldName,
                            'old' => $this->formatValue($key, $oldValue),
                            'new' => $this->formatValue($key, $newValue),
                        ];
                    }
                }
                
                return [
                    'id' => $activity->id,
                    'description' => $activity->description,
                    'event_type' => $activity->event,
                    'causer' => $activity->causer ? [
                        'id' => $activity->causer->id,
                        'name' => $activity->causer->name,
                        'email' => $activity->causer->email,
                    ] : null,
                    'changes' => $formattedChanges,
                    'created_at' => $activity->created_at->format('Y-m-d H:i:s'),
                    'created_at_human' => $activity->created_at->diffForHumans(),
                ];
            });
    }

    /**
     * Format field name for display
     *
     * @param  string  $field
     * @return string
     */
    private function formatFieldName($field)
    {
        $fieldMap = [
            'event_type_id' => 'Event Type',
            'eventable_type' => 'Event Source Type',
            'eventable_id' => 'Event Source ID',
            'additional_data' => 'Additional Data',
        ];
        
        if (isset($fieldMap[$field])) {
            return $fieldMap[$field];
        }
        
        // Convert snake_case to Title Case
        return ucwords(str_replace('_', ' ', $field));
    }

    /**
     * Format value for display
     *
     * @param  string  $field
     * @param  mixed  $value
     * @return string|array
     */
    private function formatValue($field, $value)
    {
        if (is_null($value)) {
            return '(empty)';
        }
        
        // Format dates
        if ($field === 'date' && $value) {
            try {
                return Carbon::parse($value)->format('F j, Y');
            } catch (\Exception $e) {
                return $value;
            }
        }
        
        // Format times
        if ($field === 'time' && $value) {
            try {
                return Carbon::parse($value)->format('g:i A');
            } catch (\Exception $e) {
                return $value;
            }
        }
        
        // Format event type ID
        if ($field === 'event_type_id' && $value) {
            $eventType = EventTypes::find($value);
            return $eventType ? $eventType->name : "ID: {$value}";
        }
        
        // Format notes (strip HTML and preview)
        if ($field === 'notes' && is_string($value)) {
            return $this->formatNotesForDisplay($value);
        }
        
        // Format additional_data (JSON) - return structured data for detailed comparison
        if ($field === 'additional_data' && (is_array($value) || is_object($value))) {
            return $this->formatAdditionalData($value);
        }
        
        // Convert boolean values
        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }
        
        return (string) $value;
    }

    /**
     * Format notes field for display (strip HTML and create preview)
     *
     * @param  string  $html
     * @return string
     */
    private function formatNotesForDisplay($html)
    {
        // Strip all HTML tags
        $text = strip_tags($html);
        
        // Decode HTML entities
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        // Remove excessive whitespace and normalize line breaks
        $text = preg_replace('/\s+/', ' ', $text);
        $text = trim($text);
        
        // Return empty indicator if no content
        if (empty($text)) {
            return '(empty)';
        }
        
        // Create a preview with word count if it's long
        $maxLength = 200;
        if (strlen($text) > $maxLength) {
            $text = substr($text, 0, $maxLength);
            // Try to break at last space to avoid cutting words
            $lastSpace = strrpos($text, ' ');
            if ($lastSpace !== false && $lastSpace > $maxLength - 50) {
                $text = substr($text, 0, $lastSpace);
            }
            $text .= '...';
        }
        
        return $text;
    }

    /**
     * Format additional_data for detailed display
     *
     * @param  mixed  $data
     * @return string
     */
    private function formatAdditionalData($data)
    {
        $data = is_object($data) ? (array) $data : $data;
        
        $formatted = [];
        
        // Format times/timeline
        if (isset($data['times']) && is_array($data['times'])) {
            $timesList = [];
            foreach ($data['times'] as $time) {
                $timeData = is_object($time) ? (array) $time : $time;
                if (isset($timeData['title']) && isset($timeData['time'])) {
                    try {
                        $formattedTime = Carbon::parse($timeData['time'])->format('g:i A');
                        $timesList[] = "  • {$timeData['title']}: {$formattedTime}";
                    } catch (\Exception $e) {
                        $timesList[] = "  • {$timeData['title']}: {$timeData['time']}";
                    }
                }
            }
            if (!empty($timesList)) {
                $formatted[] = "Timeline:\n" . implode("\n", $timesList);
            }
        }
        
        // Format attire
        if (isset($data['attire'])) {
            $attireText = strip_tags($data['attire']);
            $attireText = trim(preg_replace('/\s+/', ' ', $attireText));
            if (!empty($attireText)) {
                $preview = strlen($attireText) > 100 ? substr($attireText, 0, 100) . '...' : $attireText;
                $formatted[] = "Attire: {$preview}";
            }
        }
        
        // Format boolean fields - group them together
        $booleanFields = [
            'public' => 'Public',
            'outside' => 'Outside',
            'backline_provided' => 'Backline Provided',
            'production_needed' => 'Production Needed',
        ];
        
        $booleanValues = [];
        foreach ($booleanFields as $key => $label) {
            if (isset($data[$key])) {
                $booleanValues[] = "{$label}: " . ($data[$key] ? 'Yes' : 'No');
            }
        }
        if (!empty($booleanValues)) {
            $formatted[] = "Event Settings:\n  • " . implode("\n  • ", $booleanValues);
        }
        
        // Format lodging
        if (isset($data['lodging']) && is_array($data['lodging']) && !empty($data['lodging'])) {
            $lodgingCount = count($data['lodging']);
            $formatted[] = "Lodging: {$lodgingCount} " . ($lodgingCount === 1 ? 'entry' : 'entries');
        }
        
        // Format performance data
        $performanceParts = [];
        if (isset($data['performance']) && is_array($data['performance'])) {
            $perf = $data['performance'];
            if (isset($perf['songs']) && is_array($perf['songs']) && !empty($perf['songs'])) {
                $songCount = count($perf['songs']);
                $performanceParts[] = "{$songCount} " . ($songCount === 1 ? 'song' : 'songs');
            }
            if (isset($perf['charts']) && is_array($perf['charts']) && !empty($perf['charts'])) {
                $chartCount = count($perf['charts']);
                $performanceParts[] = "{$chartCount} " . ($chartCount === 1 ? 'chart' : 'charts');
            }
        }
        if (!empty($performanceParts)) {
            $formatted[] = "Performance: " . implode(", ", $performanceParts);
        }
        
        // Format wedding data
        $weddingParts = [];
        if (isset($data['wedding']) && is_array($data['wedding'])) {
            $wedding = $data['wedding'];
            if (isset($wedding['onsite'])) {
                $weddingParts[] = "Onsite Ceremony: " . ($wedding['onsite'] ? 'Yes' : 'No');
            }
            if (isset($wedding['dances']) && is_array($wedding['dances']) && !empty($wedding['dances'])) {
                $danceCount = count($wedding['dances']);
                $weddingParts[] = "{$danceCount} " . ($danceCount === 1 ? 'dance' : 'dances');
            }
        }
        if (!empty($weddingParts)) {
            $formatted[] = "Wedding: " . implode(", ", $weddingParts);
        }
        
        return !empty($formatted) ? implode("\n\n", $formatted) : 'Modified';
    }
}
