<?php

namespace App\Http\Controllers;

use App\Models\Bands;
use App\Models\EventTypes;
use App\Models\Proposal;
use App\Models\ProposalContacts;
use App\Models\ProposalPhases;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use App\Models\Proposals;
use Illuminate\Support\Str;

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

        return Inertia::render('Proposals/Index',[
            'bandsAndProposals'=>$bands[0]->with('proposals')->get(),
            'eventTypes'=>$eventTypes,
            'proposal_phases'=>$proposal_phases
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
            'locked'=>false,
            'notes'=>'',
            'key'=>Str::uuid(),
            'author_id'=>$author->id,
            'name'=>$request->name
        ]);

        $eventTypes = EventTypes::all();
        return Inertia::render('Proposals/Edit',[
            'proposal'=>$proposal,
            'eventTypes'=>$eventTypes
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
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Proposal  $proposal
     * @return \Illuminate\Http\Response
     */
    public function show(Proposals $proposal)
    {
        //
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
        return Inertia::render('Proposals/Edit',[
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
        $proposal->event_type_id = $request->event_type_id;
        $proposal->save();

        return redirect()->route('proposals')->with('successMessage', $proposal->name . ' was successfully updated');
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
