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
use App\Services\ProposalServices;
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


        $bands = $user->bandOwner;
        $bookedDates = [];
        $proposedDates = [];
        foreach($bands as $band)
        {
            foreach($band->proposals as $proposal)
            {
                $proposal->formattedDraftDate = $proposal->formattedDraftDate;
            }
            
            compact($band->proposals);
            compact($band->events);
            foreach($band->events as $event)
            {
                // $event->event_date = $event->ISODate;
                $bookedDates[] = $event;

            }
            foreach($band->proposals as $proposal)
            {

                
                // $proposal->date = $proposal->ISODate;
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
        $validated = $request->validate([
            'name' => 'required',
            'price' => 'required',
            'hours' => 'required',
        ]);

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

        $proposalService = new ProposalServices($proposal);
        $event = $proposalService->writeToCalendar();

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
        return redirect('/proposals/' . $proposal->key . '/accepted')->with('successMessage','Proposal has been accepted. Await a finalized contract');
    }

    public function sendContract(Proposals $proposal)
    {
        $status = ProposalServices::straightToContract($proposal);
        dd($status);
        return redirect()->route('proposals')->with('successMessage', $proposal->name . ' contract manually sent!');
    }
    
    private function make_pandadoc_contract($proposal)
    {
        return ProposalServices::make_pandadoc_contract($proposal);
    }  
}
