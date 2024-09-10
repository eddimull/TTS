<?php

namespace App\Http\Controllers;

use App\Models\BandEvents;
use App\Models\Bands;
use App\Models\ProposalContracts;
use App\Models\EventTypes;
use App\Models\ProposalContacts;
use App\Models\ProposalPhases;
use App\Models\Proposals;
use App\Models\recurring_proposal_dates;
use App\Models\User;
use App\Notifications\TTSNotification;
use App\Services\ProposalServices;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Inertia\Inertia;
use PDF;

class ProposalsController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Inertia\Response
     */
    public function index()
    {
        $user = Auth::user();
        $bands = $user->bandOwner;
        $bookedDates = [];
        $proposedDates = [];
        foreach ($bands as $band)
        {
            foreach ($band->proposals as $proposal)
            {
                $proposal->formattedDraftDate = $proposal->formattedDraftDate;
            }

            $compactedProposals[$band->id] = $band->proposals->toArray();
            $compactedFutureEvents[$band->id] = $band->futureEvents->toArray();

            $bookedDates = array_merge($bookedDates, $band->futureEvents->all());
            $proposedDates = array_merge($proposedDates, $band->proposals->all());
        }



        $eventTypes = EventTypes::all();
        $proposal_phases = ProposalPhases::all();


        // $bookedDates = BandEvents::where('band_id','=',$bands[0]->id)->get();
        // $proposedDates = Proposals::where('band_id','=',$bands[0]->id)->get();
        return Inertia::render('Proposals/Index', [
            'bandsAndProposals' => $bands,
            'eventTypes' => $eventTypes,
            'proposal_phases' => $proposal_phases,
            'bookedDates' => $bookedDates,
            'proposedDates' => $proposedDates
        ]);
    }

    public function create(Request $request, Bands $band)
    {
        $this->validateRequest($request);
        $proposal = $this->createProposal($request, $band);
        $this->notifyBandOwners($band, $proposal);

        return redirect()->route('proposals.edit', $proposal->key);
    }
    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    private function validateRequest(Request $request): array
    {
        return $request->validate([
            'name' => 'required',
            'price' => 'required',
            'hours' => 'required',
            'date' => 'required|date',
            'event_type_id' => 'required|exists:event_types,id',
            'notes' => 'nullable',
            'client_notes' => 'nullable',
        ]);
    }

    private function createProposal(Request $request, Bands $band): Proposals
    {
        return Proposals::create([
            'band_id' => $band->id,
            'phase_id' => 1,
            'date' => Carbon::parse($request->date),
            'hours' => $request->hours,
            'price' => $request->price,
            'event_type_id' => $request->event_type_id,
            'locked' => false,
            'location' => null,
            'notes' => $request->notes,
            'client_notes' => $request->client_notes,
            'key' => Str::uuid(),
            'author_id' => Auth::id(),
            'name' => $request->name
        ]);
    }

    private function notifyBandOwners(Bands $band, Proposals $proposal): void
    {
        $author = Auth::user();
        $notificationData = [
            'text' => "{$author->name} drafted up proposal for {$proposal->name}",
            'route' => 'proposals.edit',
            'routeParams' => $proposal->key,
            'url' => "/proposals/{$proposal->key}/edit"
        ];

        $band->load('owners.user');
        $usersToNotify = $band->owners->pluck('user');

        Notification::send($usersToNotify, new TTSNotification($notificationData));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Proposals  $proposal
     * @return \Inertia\Response
     */
    public function edit(Proposals $proposal)
    {
        $eventTypes = EventTypes::all();
        $bookedDates = BandEvents::where('band_id', '=', $proposal->band_id)->where('event_time', '>=', Carbon::now())->get();
        $proposedDates = Proposals::where('band_id', '=', $proposal->band_id)->where('date', '>=', Carbon::now())->where('id', '!=', $proposal->id)->get();
        return Inertia::render('Proposals/Edit', [
            'proposal' => $proposal,
            'eventTypes' => $eventTypes,
            'bookedDates' => $bookedDates,
            'proposedDates' => $proposedDates,
            'recurringDates' => $proposal->recurring_dates
        ]);
    }

    public function createContact(Request $request, Proposals $proposal)
    {
        $request->validate([
            'name' => 'required',
            'email' => 'required|email:rfc,dns'
        ]);

        ProposalContacts::create([
            'proposal_id' => $proposal->id,
            'email' => $request->email,
            'phonenumber' => $request->phonenumber,
            'name' => $request->name
        ]);

        return back()->with('successMessage', 'Added ' . $request->name . ' as contact');
    }


    public function editContact(Request $request, ProposalContacts $contact)
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

    public function deleteContact(ProposalContacts $contact)
    {
        $contact->delete();
        return back()->with('successMessage', 'Removed Contact');
    }


    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Client\Response
     */
    public function searchLocations(Request $request)
    {
        $googleResponse = Http::get("https://maps.googleapis.com/maps/api/place/autocomplete/json?input=" . $request->searchParams . "&key=" . Config::get('googlemaps.key') . '&sessiontoken=' . $request->sessionToken);

        return $googleResponse;
    }

    public function searchDetails(Request $request)
    {
        $googleResponse = Http::get("https://maps.googleapis.com/maps/api/place/details/json?place_id=" . $request->place_id . "&key=" . Config::get('googlemaps.key') . '&sessiontoken=' . $request->sessionToken);

        return $googleResponse;
    }


    public function accepted(Proposals $proposal)
    {
        $eventTypes = EventTypes::all();
        return Inertia::render('AcceptedProposal', [
            'proposal' => $proposal,
            'eventTypes' => $eventTypes
        ]);
    }

    public function details(Proposals $proposal)
    {

        if ($proposal->phase_id === 4)
        {
            return redirect('/proposals/' . $proposal->key . '/accepted')->withErrors('Proposal has already been accepted');
        }

        $eventTypes = EventTypes::all();
        return Inertia::render('ProposalDetails', [
            'proposal' => $proposal,
            'eventTypes' => $eventTypes
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Proposals  $proposal
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(Request $request, Proposals $proposal)
    {
        $request->validate([
            'name' => 'required',
            'date' => 'required|date',
            'hours' => 'required|numeric',
            'event_type_id' => 'required|numeric'
        ]);

        $proposal->name = $request->name;
        $proposal->date = date('Y-m-d H:i:s', strtotime($request->date));
        $proposal->hours = $request->hours;
        $proposal->location = $request->location;
        $proposal->price = $request->price;
        $proposal->notes = $request->notes;
        $proposal->client_notes = $request->client_notes;
        $proposal->event_type_id = $request->event_type_id;
        $proposal->save();

        $noTouchy = [];

        foreach ($request->recurring_dates as $date)
        {
            if (empty($date->propsal_id))
            {
                $recurringDate = recurring_proposal_dates::create([
                    'proposal_id' => $proposal->id,
                    'date' => date('Y-m-d H:i:s', strtotime($date['date']))
                ]);
            }
            else
            {
                $recurringDate = recurring_proposal_dates::find($date->id);
                $recurringDate->date = date('Y-m-d H:i:s', strtotime($date['date']));
                $recurringDate->save();
            }
            $noTouchy[] = $recurringDate->id;
        }

        foreach ($proposal->recurring_dates as $date)
        {
            if (!in_array($date->id, $noTouchy))
            {
                $date->delete();
            }
        }


        return redirect()->route('proposals')->with('successMessage', $proposal->name . ' was successfully updated');
    }

    public function sendIt(Proposals $proposal, Request $request)
    {

        foreach ($proposal->proposal_contacts as $contact)
        {

            Mail::to($contact->email)->send(new \App\Mail\Proposal($proposal, $request->message));
        }

        $proposal->phase_id = 3;
        $proposal->save();
        $author = Auth::user();
        $band = Bands::find($proposal->band_id);

        foreach ($band->owners as $owner)
        {
            $user = User::find($owner->user_id);
            $user->notify(new TTSNotification([
                'text' => $author->name . ' sent out proposal for ' . $proposal->name,
                'route' => 'proposals',
                'routeParams' => '',
                'url' => '/proposals/'
            ]));
        }

        return redirect()->route('proposals')->with('successMessage', $proposal->name . ' sent to clients!');
    }

    public function finalize(Proposals $proposal)
    {
        $proposal->phase_id = 2;
        $proposal->save();
        return redirect()->route('proposals', ['open' => $proposal->id])->with('successMessage', $proposal->name . ' has been finalized.');
    }
    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Proposals  $proposal
     * @return \Illuminate\Http\RedirectResponse
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
        $proposalService = new ProposalServices($proposal);

        $band = Bands::find($proposal->band_id);

        foreach ($band->owners as $owner)
        {
            $user = User::find($owner->user_id);
            $user->notify(new TTSNotification([
                'text' => $request->person . ' just accepted proposal for ' . $proposal->name,
                'route' => 'proposals',
                'routeParams' => '',
                'url' => '/proposals/'
            ]));
        }

        $proposalService->make_pandadoc_contract();
        $proposal->phase_id = 5;
        $proposal->save();
        return redirect('/proposals/' . $proposal->key . '/accepted')->with('successMessage', 'Proposal has been accepted. Await a finalized contract');
    }

    public function sendContract(Request $request, Proposals $proposal)
    {
        if ($proposal->phase_id != 4)
        {
            return redirect()->route('proposals')->withErrors('Proposal has not been approved. Cannot send out.');
        }

        $proposalService = new ProposalServices($proposal);
        $proposalService->make_pandadoc_contract();
        $proposal->phase_id = 5;
        $proposal->save();
        return redirect()->route('proposals')->with('successMessage', $proposal->name . ' contract manually sent!');
    }
}
