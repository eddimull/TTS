<?php
namespace App\Services;

use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use App\Models\State;
use App\Models\BandEvents;
use App\Models\Bands;
use App\Models\Contracts;
use Illuminate\Support\Facades\Config;
use Spatie\GoogleCalendar\Event as CalendarEvent;
use Illuminate\Support\Facades\Auth;
use App\Notifications\TTSNotification;
use App\Models\User;
use PDF;
use App\Models\EventContacts;
use App\Models\Proposals;

class ProposalServices
{
    protected $proposal;
    public function __construct($proposal)
    {
        $this->proposal = $proposal;
    }


    private function addContactsToEvent($eventId)
    {
        $contacts = $this->proposal->proposal_contacts;
        foreach($contacts as $contact)
        {
            EventContacts::create([
                'event_id'=>$eventId,
                'name'=>$contact->name,
                'phonenumber'=>$contact->phonenumber,
                'email'=>$contact->email
            ]);
        }
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

        $this->addContactsToEvent($event->id);

        return $event;
    }


    static function make_pandadoc_contract(Proposals $proposal)
    {
        $pdf = PDF::loadView('contract',['proposal'=>$proposal]);
        $base64PDF = base64_encode($pdf->output());
        $imagePath = $proposal->band->site_name . '/' . $proposal->name . '_contract_' . time() . '.pdf';

        $path = Storage::disk('s3')->put($imagePath,
        base64_decode($base64PDF),
        ['visibility'=>'public']);

        $recipients = [];
        foreach($proposal->proposal_contacts as $index => $contact)
        {
            $fullName = $contact->name;
            $nameParts = preg_split('/\s+/', $fullName);
            $firstName = $nameParts[0];
            $lastName = $nameParts[1];

            $recipients[] = [
                "email"=> $contact->email,
                "first_name"=>$firstName,
                "last_name"=>$lastName,
                "role"=> "user$index"
            ];
        }
        
        
        $body =  [
            "name"=> "Contract for " . $proposal->band->name . " for " . $proposal->name,
            "url"=>Storage::disk('s3')->url($imagePath),
            "tags"=> [
            "tag_1"
            ],
            "recipients"=> $recipients,
            "fields"=> [  
                "name"=> [  
                    "value"=> "",
                    "role"=> "user0"
                ]
            ],
            "parse_form_fields"=> false
        ];

        
        
        $response = Http::withHeaders([
            'Authorization'=>'API-Key ' . env('PANDADOC_KEY')
        ])
        ->acceptJson()
        ->post('https://api.pandadoc.com/public/v1/documents',$body);
        
        $response->throw();

        $data = $response->json();

        $uploadedDocumentId = $data['id'];
        
        // if($proposal->proposal_contacts[0]->name === 'TESTING')
        // {
        //     $sent = true;
        // }
        // else
        // {
        //     $sent = Http::withHeaders([
        //         'Authorization'=>'API-Key '  . env('PANDADOC_KEY')
        //         ])->post('https://api.pandadoc.com/public/v1/documents/' . $uploadedDocumentId . '/send',[
        //             "messsage"=>'Please sign this contract so we can make this official!',
        //             "subject"=>'Contract for ' . $proposal->band->name
        //         ]);
        // }

        Contracts::create([
            'proposal_id'=>$proposal->id,
            'envelope_id'=>$uploadedDocumentId,
            'status'=>'sent',
            'image_url'=>Storage::disk('s3')->url($imagePath)
        ]);

        return $data;
    }

    static function straightToContract(Proposals $proposal)
    {
        
        $data = self::make_pandadoc_contract($proposal);
        $proposal->phase_id = 5;
        $proposal->save();
        
        
        Notification::send($proposal->band->owners, new TTSNotification([
            'text'=>'A new contract has been created for ' . $proposal->name . '.',
            'route'=>'proposals',
            'routeParams'=>'',
            'url'=>'/proposals'
        ]));
        
        return $data;
    }

}