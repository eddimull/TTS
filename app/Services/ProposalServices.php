<?php
namespace App\Services;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use App\Models\State;
use App\Models\BandEvents;
use App\Models\Bands;
use Illuminate\Support\Facades\Config;
use Spatie\GoogleCalendar\Event as CalendarEvent;
use Illuminate\Support\Facades\Auth;
use App\Notifications\TTSNotification;
use App\Models\User;


class ProposalServices
{
    protected $proposal;
    public function __construct($proposal)
    {
        $this->proposal = $proposal;
    }

    public function writeToCalendar()
    {
        $sessionToken = Str::random();
        $googleResponse = Http::get("https://maps.googleapis.com/maps/api/place/autocomplete/json",[
            'input'=> $this->proposal->location,
            'key' => $_ENV['GOOGLE_MAPS_API_KEY'],
            'sessiontoken' => $sessionToken
        ]);
        $parsedResponse = json_decode($googleResponse->body());
        $usableAddress = [
            'venue'=>'Unnamed Venue',
            'street_number' => '',
            'route' => '',
            'locality' => '',
            'state' => 'Louisiana',
            'postal_code' => ''
        ];
        if($parsedResponse->status !== 'INVALID_REQUEST' && $parsedResponse->status !== 'ZERO_RESULTS')
        {
            $usableAddress['venue'] = $parsedResponse->predictions[0]->structured_formatting->main_text;
            $place_id = $parsedResponse->predictions[0]->place_id;
            $detailedResponse = Http::get("https://maps.googleapis.com/maps/api/place/details/json",[
                'place_id'=>$place_id,
                'key' => $_ENV['GOOGLE_MAPS_API_KEY'],
                'sessiontoken'=> $sessionToken
            ]);
            $parsedDetails = json_decode($detailedResponse->body());

            if($parsedDetails->status !== 'INVALID_REQUEST')
            {
                $addressComponents = $parsedDetails->result->address_components;                 
                foreach($addressComponents as $component)
                {
                    
                    if(array_search('street_number', $component->types) !== false)
                    {
                        $usableAddress['street_number'] = $component->long_name;                                        
                    }
                    if(array_search('route', $component->types) !== false)
                    {
                        $usableAddress['route'] = $component->long_name;                                
                    }
                    if(array_search('locality', $component->types) !== false)
                    {
                        $usableAddress['locality'] = $component->long_name;                                
                    }
                    if(array_search('administrative_area_level_1', $component->types) !== false)
                    {
                        $usableAddress['state'] = $component->long_name;                                
                    }
                    if(array_search('postal_code', $component->types) !== false)
                    {
                        $usableAddress['postal_code'] = $component->long_name;                                
                    }
                }   
            }
            

        }
        
        $state = State::where('state_name',$usableAddress['state'])->first();

        
        $event = BandEvents::create([
            'band_id' => $this->proposal->band->id,
            'event_name' => $this->proposal->name,
            'venue_name' => $usableAddress['venue'],
            'first_dance' => 'TBD',
            'father_daughter' => 'TBD',
            'mother_groom' => 'TBD',
            'money_dance' => 'TBD',
            'bouquet_garter' => 'TBD',
            'address_street' => $usableAddress['street_number'] . ' ' . $usableAddress['route'],
            'production_needed'=>true,
            'backline_provided'=>false,
            'zip' => $usableAddress['postal_code'],
            'notes' => $this->proposal->notes,
            'event_time' => date('Y-m-d H:i:s',strtotime($this->proposal->date)),
            'band_loadin_time' =>  date('Y-m-d H:i:s',strtotime($this->proposal->date)),
            'rhythm_loadin_time' => date('Y-m-d H:i:s',strtotime($this->proposal->date)),
            'production_loadin_time' => date('Y-m-d H:i:s',strtotime($this->proposal->date)),
            'pay' => $this->proposal->price,
            'depositReceived' => true,
            'event_key' => Str::uuid(),
            'public' => false,
            'event_type_id' => $this->proposal->event_type_id,
            'lodging' => false,
            'state_id' => $state->state_id,
            'city' => $usableAddress['locality'],
            'colorway_id'=>0,
            'quiet_time'=> date('Y-m-d H:i:s',strtotime($this->proposal->date)),
            'end_time'=> date('Y-m-d H:i:s',strtotime($this->proposal->date . '+ ' . $this->proposal->hours . ' hours')),
            'ceremony_time'=> date('Y-m-d H:i:s',strtotime($this->proposal->date)),
            'outside'=>false,
            'second_line'=>false,
            'onsite'=>false,
            'event_key'=>Str::uuid()
        ]);

        $this->proposal->event_id = $event->id;
        $this->proposal->save();

        $band = Bands::find($event->band_id);
        if($band->calendar_id !== '' && $band->calendar_id !== null)
        {

            Config::set('google-calendar.service_account_credentials_json',storage_path('/app/google-calendar/service-account-credentials.json'));
            Config::set('google-calendar.calendar_id',$band->calendar_id);
            
            // dd(Carbon::parse($event->event_time));

            if($event->google_calendar_event_id !== null)
            {
                $calendarEvent = CalendarEvent::find($event->google_calendar_event_id);
            }
            else
            {
                $calendarEvent = new CalendarEvent;
            }
            $calendarEvent->name = $event->event_name;

            $startTime = Carbon::parse($event->event_time);
            $endDateTimeFixed = date('Y-m-d',strtotime($event->event_time)) . ' ' . date('H:i:s', strtotime($event->end_time));
            if($endDateTimeFixed < $startTime)//when events end after midnight
            {
                $endDateTimeFixed = date('Y-m-d',strtotime($event->event_time . ' +1 day')) . ' ' . date('H:i:s', strtotime($event->end_time));
            }
            $endTime = Carbon::parse($endDateTimeFixed);
            $calendarEvent->startDateTime = $startTime;
            $calendarEvent->endDateTime = $endTime;   
            $calendarEvent->description = 'https://tts.band/events/' . $event->event_key . '/advance';
            $google_id = $calendarEvent->save();  
            $event->google_calendar_event_id = $google_id->id;
            $event->save();
        }

        return $event;
        // $editor = Auth::user();
        // compact($band->owners);
        // foreach($band->owners as $owner)
        // {
        //    $user = User::find($owner->user_id);
        //    $user->notify(new TTSNotification([
        //     'text'=>$editor->name . ' added ' . $event->event_name . ' created from proposal',
        //     'route'=>'events.advance',
        //     'routeParams'=>$event->event_key,
        //     'url'=>'/events/' . $event->event_key . '/advance'
        //     ]));
        // }
    }

}