<?php

namespace App\Http\Controllers;

use App\Models\BandEvents;
use App\Models\Bands;
use App\Models\EventTypes;
use App\Models\Proposal;
use App\Models\ProposalContacts;
use App\Models\ProposalPhases;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use App\Models\Proposals;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;
use App\Models\User;
use App\Notifications\TTSNotification;


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
                   
        $eventTypes = EventTypes::all();
        $proposal_phases = ProposalPhases::all();
        
        $bookedDates = BandEvents::where('band_id','=',$bands[0]->id)->get();
        $proposedDates = Proposals::where('band_id','=',$bands[0]->id)->get();

        return Inertia::render('Proposals/Index',[
            'bandsAndProposals'=>$bands[0]->with('proposals')->get(),
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
            'notes'=>'',
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
        return Inertia::render('Proposals/Edit',[
            'proposal'=>$proposal,
            'eventTypes'=>$eventTypes,
            'bookedDates'=>$bookedDates,
            'proposedDates'=>$proposedDates
        ]);
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
            'proposedDates'=>$proposedDates
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




    public function details(Proposals $proposal)
    {
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
        $proposal->event_type_id = $request->event_type_id;
        $proposal->save();

        return redirect()->route('proposals')->with('successMessage', $proposal->name . ' was successfully updated');
    }

    public function sendIt(Proposals $proposal)
    {
        
        foreach($proposal->proposal_contacts as $contact)
        {
            
            Mail::to($contact->email)->send(new \App\Mail\Proposal($proposal));
            
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
        return redirect()->route('proposals')->with('successMessage', $proposal->name . ' has been finalized.');
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
}
