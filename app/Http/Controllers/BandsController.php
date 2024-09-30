<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Bands;
use App\Models\BandOwners;
use App\Models\stripe_accounts;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Notifications\TTSNotification;
use App\Services\CalendarService;

class BandsController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        // $bands = Bands::select('bands.*')->join('band_owners','bands.id','=','band_owners.band_id')->where('user_id',Auth::id())->get();
        $user = Auth::user();
        // $bandOwner = $user->ban

        return Inertia::render('Band/Index', [
            'bands' => $user->bandOwner
        ]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
        return Inertia::render('Band/Create');
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
        $request->validate([
            'name' => 'required',
            'site_name' => 'required|unique:bands,site_name'
        ]);

        // dd($request);
        $createdBand = Bands::create([
            'name' => $request->name,
            'site_name' => $request->site_name
        ]);

        BandOwners::create([
            'band_id' => $createdBand->id,
            'user_id' => Auth::id()
        ]);

        return redirect()->route('bands')->with('successMessage', 'Band was successfully added');
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
    public function edit(Bands $band)
    {
        // Load the relationships if they're not already loaded
        $band->load('owners.user', 'members.user', 'pendingInvites', 'stripe_accounts');

        // Merge additional data into the $band object
        $band->owners_with_users = $band->owners->map(function ($owner)
        {
            $owner->user_data = $owner->user;
            return $owner;
        });

        $band->members_with_users = $band->members->map(function ($member)
        {
            $member->user_data = $member->user;
            return $member;
        });

        return Inertia::render('Band/Edit', [
            'band' => $band
        ]);
    }

    public function syncCalendar(Bands $band)
    {
        $calService = new CalendarService($band);
        $calService->syncEvents();

        return back()->with('successMessage', 'Events written to your calendar!');
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
        $band = Bands::find($id);
        $validation_rules = [
            'name' => 'required',
        ];
        if ($band->site_name != $request->site_name)
        {
            $validation_rules['site_name'] = 'required|unique:bands,site_name';
        }

        $request->validate($validation_rules);


        $band->name = $request->name;
        $band->site_name = $request->site_name;
        $band->calendar_id = $request->calendar_id;

        $band->save();

        return redirect()->route('bands')->with('successMessage', $band->name . ' was successfully updated');
    }


    public function deleteOwner(Bands $band, $ownerParam)
    {


        $owner = BandOwners::where('user_id', '=', $ownerParam)->where('band_id', '=', $band->id)->first();
        /** @var \App\Models\User $user */
        $author = Auth::user();
        if ($author->ownsBand($band->id))
        {

            foreach ($band->owners as $bandOwner)
            {
                $inviteOwnerUser = User::find($bandOwner->user_id);
                $inviteOwnerUser->notify(new TTSNotification([
                    'text' => $author->name . ' removed ' . $owner->user->name . ' an owner of ' . $band->name,
                    'route' => 'bands.edit',
                    'routeParams' => $band->id,
                    'url' => '/bands/' . $band->id . '/edit'
                ]));
            }

            $owner->delete();
            return back()->with('successMessage', 'User removed from band owners');
        }
        else
        {
            return back()->withErrors(['You are not authorized to remove the owner of this band.']);
        }
    }

    public function uploadLogo(Bands $band, Request $request)
    {
        $request->validate([
            'files.*' => 'required|image'
        ]);
        $author = Auth::user();

        if ($author->isOwner($band->id))
        {
            $imageName = time() . $band->name . 'logo.' . $request->logo[0]->extension();

            $imagePath = $request->logo[0]->storeAs($band->site_name, $imageName, 's3');

            $band->logo = '/images/' . $imagePath;


            $band->save();


            foreach ($band->owners as $owner)
            {
                $user = User::find($owner->user_id);
                $user->notify(new TTSNotification([
                    'text' => $author->name . ' updated the logo for ' . $band->name,
                    'route' => 'bands',
                    'routeParams' => null,
                    'url' => '/bands/' . $band->id . '/edit'
                ]));
            }

            return redirect()->back()->with('successMessage', 'Updated Logo! (no need to save)');
        }

        return back()->withErrors('You do not have privileges to update the logo for this band');
    }

    public function setupStripe(Bands $band, Request $request)
    {
        \Stripe\Stripe::setApiKey(env('STRIPE_KEY'));

        $account = \Stripe\Account::create([
            'type' => 'standard',
        ]);

        $account_links = \Stripe\AccountLink::create([
            'account' => $account->id,
            'refresh_url' => url('/login'),
            'return_url' => url('/bands/' . $band->id . '/edit'),
            'type' => 'account_onboarding',
        ]);

        stripe_accounts::create([
            'band_id' => $band->id,
            'stripe_account_id' => $account->id,
            'status' => 'incomplete'
        ]);
        return \Redirect::away($account_links->url);
    }

    public function contacts(Bands $band)
    {
        $band->load('contacts');
        return $band->contacts;
    }
}
