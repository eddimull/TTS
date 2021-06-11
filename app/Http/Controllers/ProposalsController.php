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
use LaravelDocusign\Facades\DocuSign;
use DocuSign\eSign\Model\EnvelopeDefinition;
use PDF;

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

        $client = DocuSign::create();
        $sent = $client->envelopes->createEnvelopeWithHttpInfo($this->make_envelope_from_docusign($proposal));

        return redirect('/proposals/' . $proposal->key . '/accepted')->with('successMessage','Proposal has been accepted. Await a finalized contract');
    }

    private function make_envelope_from_docusign($proposal): EnvelopeDefinition
    {
        $pdf = PDF::loadView('contract',['proposal'=>$proposal]);
        $base64PDF = base64_encode($pdf->output());

        # Create the envelope definition
        $envelope_definition = new \DocuSign\eSign\Model\EnvelopeDefinition([
           'email_subject' => 'Contract for ' . $proposal->band->name,
           'email_blurb'=>'Please sign this contract so we can make this official!'
        ]);
        # read files 2 and 3 from a local directory
        # The reads could raise an exception if the file is not available!


        $document = new \DocuSign\eSign\Model\Document([  # create the DocuSign document object
            'document_base64' => $base64PDF,
            'name' => 'Contract for ' . $proposal->band->name,  # can be different from actual file name
            'file_extension' => 'pdf',  # many different document types are accepted
            'document_id' => '1'  # a label used to reference the doc
        ]);
        # The order in the docs array determines the order in the envelope
        $envelope_definition->setDocuments([$document]);
        
        foreach($proposal->proposal_contacts as $contact)
        {
            
            Mail::to($contact->email)->send(new \App\Mail\Proposal($proposal));
            
        }
        # Create the signer recipient model
        $signer1 = null;
        # routingOrder (lower means earlier) determines the order of deliveries
        # to the recipients. Parallel routing order is supported by using the
        # same integer as the order for two or more recipients.
        $carbonCopies = [];

        $contactIndex = 0;
        foreach($proposal->proposal_contacts as $contact)
        {
            $contactIndex += 1;

            if($contactIndex === 1)
            {
                $signer1 = new \DocuSign\eSign\Model\Signer([
                    'email' => $contact->email, 'name' => $contact->name,
                    'recipient_id' => "1", 'routing_order' => "1"]);
            }
            else
            {
                $carbonCopies[] = new \DocuSign\eSign\Model\CarbonCopy([
                    'email' => $contact->email, 'name' => $contact->name,
                    'recipient_id' => $contactIndex, 'routing_order' => $contactIndex]);
            }
        }

        # Create signHere fields (also known as tabs) on the documents,
        # We're using anchor (autoPlace) positioning
        #
        # The DocuSign platform searches throughout your envelope's
        # documents for matching anchor strings. So the
        # signHere2 tab will be used in both document 2 and 3 since they
        #  use the same anchor string for their "signer 1" tabs.
        $sign_here1 = new \DocuSign\eSign\Model\SignHere([
            'anchor_string' => 'Signature:', 'anchor_units' => 'pixels',
            'anchor_y_offset' => '10', 'anchor_x_offset' => '40']);
       

        # Add the tabs model (including the sign_here tabs) to the signer
        # The Tabs object wants arrays of the different field/tab types
        $signer1->setTabs(new \DocuSign\eSign\Model\Tabs([
            'sign_here_tabs' => [$sign_here1]]));

        # Add the recipients to the envelope object
        $recipients = new \DocuSign\eSign\Model\Recipients([
            'signers' => [$signer1], 'carbon_copies' => $carbonCopies]);
        $envelope_definition->setRecipients($recipients);

        # Request that the envelope be sent by setting |status| to "sent".
        # To request that the envelope be created as a draft, set to "created"
        $envelope_definition->setStatus('sent');

        return $envelope_definition;
    }    
}
