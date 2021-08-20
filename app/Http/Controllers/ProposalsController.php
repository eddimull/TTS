<?php

namespace App\Http\Controllers;

use App\Models\BandEvents;
use App\Models\Bands;
use App\Models\Contracts;
use App\Models\EventTypes;
use App\Models\Proposal;
use App\Models\ProposalContacts;
use App\Models\ProposalPhases;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use App\Models\Proposals;
use App\Models\recurring_proposal_dates;
use App\Models\State;
use Spatie\GoogleCalendar\Event as CalendarEvent;
use Carbon\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;
use App\Models\User;
use App\Notifications\TTSNotification;
use LaravelDocusign\Facades\DocuSign;
use DocuSign\eSign\Model\EnvelopeDefinition;
use PDF;
use Illuminate\Support\Facades\Storage;

class ProposalsController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $user = Auth::user();

        // $bands = $user->bandOwner->with('proposals')->get()->pluck('proposals')->flatten();
        // $bands = $user->bandOwner();
        // $band = Bands::with('proposals')->find(1);
        $bands = $user->bandOwner;
        $bookedDates = [];
        $proposedDates = [];
        foreach($bands as $band)
        {
            compact($band->proposals);
            compact($band->events);
            foreach($band->events as $event)
            {
                $bookedDates[] = $event;

            }
            foreach($band->proposals as $proposal)
            {
                $proposedDates[] = $proposal;

            }
        }


        
        $eventTypes = EventTypes::all();
        $proposal_phases = ProposalPhases::all();
        
        // $bookedDates = BandEvents::where('band_id','=',$bands[0]->id)->get();
        // $proposedDates = Proposals::where('band_id','=',$bands[0]->id)->get();
        return Inertia::render('Proposals/Index',[
            'bandsAndProposals'=>$bands,
            'eventTypes'=>$eventTypes,
            'proposal_phases'=>$proposal_phases,
            'bookedDates'=>$bookedDates,
            'proposedDates'=>$proposedDates
        ]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request, Bands $band)
    {
        $author = Auth::user();
        $proposal = Proposals::create([
            'band_id'=>$band->id,
            'phase_id'=>1,
            'date'=>date('Y-m-d H:i:s',strtotime($request->date)),
            'hours'=>$request->hours,
            'price'=>$request->price,
            'event_type_id'=>$request->event_type_id,
            'locked'=>false,
            'location'=>null,
            'notes'=>$request->notes,
            'client_notes'=>$request->client_notes,
            'key'=>Str::uuid(),
            'author_id'=>$author->id,
            'name'=>$request->name
        ]);


        foreach($band->owners as $owner)
        {
           $user = User::find($owner->user_id);
           $user->notify(new TTSNotification([
            'text'=>$author->name . ' drafted up proposal for ' . $proposal->name,
            'route'=>'proposals.edit',
            'routeParams'=>$proposal->key,
            'url'=>'/proposals/' . $proposal->key . '/edit'
            ]));
        }

        $bookedDates = BandEvents::where('band_id','=',$proposal->band_id)->get();
        $proposedDates = Proposals::where('band_id','=',$proposal->band_id)->where('id','!=',$proposal->id)->get();

        compact($proposal->proposal_contacts);
        $eventTypes = EventTypes::all();
        return redirect('/proposals/' . $proposal->key . '/edit');
        // return Inertia::render('Proposals/Edit',[
        //     'proposal'=>$proposal,
        //     'eventTypes'=>$eventTypes,
        //     'bookedDates'=>$bookedDates,
        //     'proposedDates'=>$proposedDates,
        //     'recurringDates'=>$proposal->recurring_dates
        // ]);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Proposal  $proposal
     * @return \Illuminate\Http\Response
     */
    public function edit(Proposals $proposal)
    {
        $eventTypes = EventTypes::all();
        $bookedDates = BandEvents::where('band_id','=',$proposal->band_id)->get();
        $proposedDates = Proposals::where('band_id','=',$proposal->band_id)->where('id','!=',$proposal->id)->get();
        return Inertia::render('Proposals/Edit',[
            'proposal'=>$proposal,
            'eventTypes'=>$eventTypes,
            'bookedDates'=>$bookedDates,
            'proposedDates'=>$proposedDates,
            'recurringDates'=>$proposal->recurring_dates
        ]);
    }

    public function createContact(Request $request, Proposals $proposal)
    {
        $request->validate([
            'name'=>'required',
            'email'=>'required|email:rfc,dns'
        ]);

        ProposalContacts::create([
            'proposal_id'=>$proposal->id,
            'email'=>$request->email,
            'phonenumber'=>$request->phonenumber,
            'name'=>$request->name
        ]);

        return back()->with('successMessage','Added ' . $request->name . ' as contact');
    }


    public function editContact(Request $request, ProposalContacts $contact)
    {
        $request->validate([
            'name'=>'required',
            'email'=>'required|email:rfc,dns'
        ]);
        $contact->email = $request->email;
        $contact->name = $request->name;
        $contact->phonenumber = $request->phonenumber;
        $contact->save();
        return back()->with('successMessage','Updated ' . $contact->name);
    }

    public function deleteContact(ProposalContacts $contact)
    {
        $contact->delete();
        return back()->with('successMessage','Removed Contact');
    }


    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function searchLocations(Request $request)
    {
        $googleResponse = Http::get("https://maps.googleapis.com/maps/api/place/autocomplete/json?input=" . $request->searchParams . "&key=" . $_ENV['GOOGLE_MAPS_API_KEY'] . '&sessiontoken=' . $request->sessionToken);
    
        return $googleResponse;
    }

    public function searchDetails(Request $request)
    {
        $googleResponse = Http::get("https://maps.googleapis.com/maps/api/place/details/json?place_id=" . $request->place_id . "&key=" . $_ENV['GOOGLE_MAPS_API_KEY'] . '&sessiontoken=' . $request->sessionToken);
    
        return $googleResponse;
    }


    public function accepted(Proposals $proposal)
    {
        $eventTypes = EventTypes::all();
        return Inertia::render('AcceptedProposal',[
            'proposal'=>$proposal,
            'eventTypes'=>$eventTypes
        ]);
    }

    public function details(Proposals $proposal)
    {

        if($proposal->phase_id === 4)
        {
            return redirect('/proposals/' . $proposal->key . '/accepted')->withErrors('Proposal has already been accepted');
        }

        $eventTypes = EventTypes::all();
        return Inertia::render('ProposalDetails',[
            'proposal'=>$proposal,
            'eventTypes'=>$eventTypes
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Proposal  $proposal
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Proposals $proposal)
    {
        $request->validate([
            'name'=>'required',
            'date'=>'required|date',
            'hours'=>'required|numeric',
            'event_type_id'=>'required|numeric'
        ]);
        
        $proposal->name = $request->name;
        $proposal->date = date('Y-m-d H:i:s',strtotime($request->date));
        $proposal->hours = $request->hours;
        $proposal->location = $request->location;
        $proposal->price = $request->price;
        $proposal->notes = $request->notes;
        $proposal->client_notes = $request->client_notes;
        $proposal->event_type_id = $request->event_type_id;
        $proposal->save();
        
        $noTouchy = []; 

        foreach($request->recurring_dates as $date)
        {
            if(empty($date->propsal_id))
            {
                $recurringDate = recurring_proposal_dates::create([
                    'proposal_id'=>$proposal->id,
                    'date'=>date('Y-m-d H:i:s',strtotime($date['date']))
                ]);
                
            }
            else
            {
                $recurringDate = recurring_proposal_dates::find($date->id);
                $recurringDate->date = date('Y-m-d H:i:s',strtotime($date['date']));
                $recurringDate->save();
            }
            $noTouchy[] = $recurringDate->id;
        }

        foreach($proposal->recurring_dates as $date)
        {
            if(!in_array($date->id,$noTouchy))
            {
                $date->delete();
            }
        }
        

        return redirect()->route('proposals')->with('successMessage', $proposal->name . ' was successfully updated');
    }

    public function sendIt(Proposals $proposal, Request $request)
    {
        
        foreach($proposal->proposal_contacts as $contact)
        {
            
            Mail::to($contact->email)->send(new \App\Mail\Proposal($proposal,$request->message));
            
        }

        $proposal->phase_id = 3;
        $proposal->save();
        $author = Auth::user();
        $band = Bands::find($proposal->band_id);

        foreach($band->owners as $owner)
        {
           $user = User::find($owner->user_id);
           $user->notify(new TTSNotification([
            'text'=>$author->name . ' sent out proposal for ' . $proposal->name,
            'route'=>'proposals',
            'routeParams'=>'',
            'url'=>'/proposals/'
            ]));
        }

        return redirect()->route('proposals')->with('successMessage', $proposal->name . ' sent to clients!');
        
    }

    public function finalize(Proposals $proposal)
    {
        $proposal->phase_id = 2;
        $proposal->save();
        return redirect()->route('proposals',['open'=>$proposal->id])->with('successMessage', $proposal->name . ' has been finalized.');
    }
    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Proposal  $proposal
     * @return \Illuminate\Http\Response
     */
    public function destroy(Proposals $proposal)
    {
        $name = $proposal->name;
        $proposal->delete();
        return redirect()->route('proposals')->with('successMessage', $name . ' has been brutally destroyed.');
    }

    public function writeToCalendar(Proposals $proposal)
    {

        // BandEvents::create({})

        $sessionToken = Str::random();
        $googleResponse = Http::get("https://maps.googleapis.com/maps/api/place/autocomplete/json",[
            'input'=> $proposal->location,
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
        if($parsedResponse->status !== 'INVALID_REQUEST')
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
            'band_id' => $proposal->band->id,
            'event_name' => $proposal->name,
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
            'notes' => $proposal->notes,
            'event_time' => date('Y-m-d H:i:s',strtotime($proposal->date)),
            'band_loadin_time' =>  date('Y-m-d H:i:s',strtotime($proposal->date)),
            'rhythm_loadin_time' => date('Y-m-d H:i:s',strtotime($proposal->date)),
            'production_loadin_time' => date('Y-m-d H:i:s',strtotime($proposal->date)),
            'pay' => $proposal->price,
            'depositReceived' => true,
            'event_key' => Str::uuid(),
            'public' => false,
            'event_type_id' => $proposal->event_type_id,
            'lodging' => false,
            'state_id' => $state->state_id,
            'city' => $usableAddress['locality'],
            'colorway_id'=>0,
            'quiet_time'=> date('Y-m-d H:i:s',strtotime($proposal->date)),
            'end_time'=> date('Y-m-d H:i:s',strtotime($proposal->date . '+ ' . $proposal->hours . ' hours')),
            'ceremony_time'=> date('Y-m-d H:i:s',strtotime($proposal->date)),
            'outside'=>false,
            'second_line'=>false,
            'onsite'=>false,
            'event_key'=>Str::uuid()
        ]);

        $proposal->event_id = $event->id;
        $proposal->save();

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

        $editor = Auth::user();
        compact($band->owners);
        foreach($band->owners as $owner)
        {
           $user = User::find($owner->user_id);
           $user->notify(new TTSNotification([
            'text'=>$editor->name . ' added ' . $event->event_name . ' created from proposal',
            'route'=>'events.advance',
            'routeParams'=>$event->event_key,
            'url'=>'/events/' . $event->event_key . '/advance'
            ]));
        }

        return redirect('/events/' . $event->event_key . '/edit');
    }

    public function accept(Request $request, Proposals $proposal)
    {
        $proposal->phase_id = 4;
        $proposal->save();

        $band = Bands::find($proposal->band_id);

        foreach($band->owners as $owner)
        {
           $user = User::find($owner->user_id);
           $user->notify(new TTSNotification([
            'text'=>$request->person . ' just accepted proposal for ' . $proposal->name,
            'route'=>'proposals',
            'routeParams'=>'',
            'url'=>'/proposals/'
            ]));
        }

        $this->make_pandadoc_contract($proposal);
        $proposal->phase_id = 5;
        $proposal->save();
        return redirect('/proposals/' . $proposal->key . '/accepted')->with('successMessage','Proposal has been accepted. Await a finalized contract');
    }

    public function sendContract(Request $request, Proposals $proposal)
    {
        if($proposal->phase_id != 4)
        {
            return redirect()->route('proposals')->withErrors('Proposal has not been approved. Cannot send out.');
        }
        
        $this->make_pandadoc_contract($proposal);
        $proposal->phase_id = 5;
        $proposal->save();
        return redirect()->route('proposals')->with('successMessage', $proposal->name . ' contract manually sent!');
    }
    private function make_pandadoc_contract($proposal)
    {
        $pdf = PDF::loadView('contract',['proposal'=>$proposal]);
        $base64PDF = base64_encode($pdf->output());
        $imagePath = $proposal->band->site_name . '/' . $proposal->name . '_contract_' . time() . '.pdf';

        $path = Storage::disk('s3')->put($imagePath,
        base64_decode($base64PDF),
        ['visibility'=>'public']);

        $body =  [
            "name"=> "Contract for " . $proposal->band->name,
            "url"=>Storage::disk('s3')->url($imagePath),
            "tags"=> [
            "tag_1"
            ],
        "recipients"=> [  
            [  
                "email"=> $proposal->proposal_contacts[0]->email,
                "first_name"=>$proposal->proposal_contacts[0]->name,
                "last_name"=>".",
                "role"=> "user"
            ]
        ],
        "fields"=> [  
            "name"=> [  
                "value"=> $proposal->proposal_contacts[0]->name,
                "role"=> "user"
            ]
        ],
        "parse_form_fields"=> false
        ];

        

        $response = Http::withHeaders([
            'Authorization'=>'API-Key ' . env('PANDADOC_KEY')
        ])
        ->acceptJson()
        ->post('https://api.pandadoc.com/public/v1/documents',$body);
        

        sleep(5);
        $uploadedDocumentId = $response['id'];

        // $sent = Http::withHeaders([
        //     'Authorization'=>'API-Key ' . env('PANDADOC_KEY')
        // ])->post('https://api.pandadoc.com/https://dev.tts.band/pandadocWebhook',[
        //     "messsage"=>'Please sign this contract so we can make this official!',
        //     "subject"=>'Contract for ' . $proposal->band->name
        // ]);

        $sent = Http::withHeaders([
            'Authorization'=>'API-Key '  . env('PANDADOC_KEY')
        ])->post('https://api.pandadoc.com/public/v1/documents/' . $uploadedDocumentId . '/send',[
            "messsage"=>'Please sign this contract so we can make this official!',
            "subject"=>'Contract for ' . $proposal->band->name
        ]);

        Contracts::create([
            'proposal_id'=>$proposal->id,
            'envelope_id'=>$uploadedDocumentId,
            'status'=>'sent',
            'image_url'=>Storage::disk('s3')->url($imagePath)
        ]);

        return $sent;
    }  
}
