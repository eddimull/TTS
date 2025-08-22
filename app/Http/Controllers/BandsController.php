<?php

namespace App\Http\Controllers;

use App\Models\User;
use Inertia\Inertia;
use App\Models\Bands;
use App\Models\BandOwners;
use Illuminate\Http\Request;
use App\Models\StripeAccounts;
use App\Services\CalendarService;
use Illuminate\Support\Facades\Auth;
use App\Notifications\TTSNotification;
use App\Http\Requests\CreateCalendarRequest;

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
    public function edit(Bands $band, $setting = null)
    {   
        // Load the relationships if they're not already loaded
        $band->load('owners.user', 'members.user', 'pendingInvites', 'stripe_accounts', 'calendars');

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
            'band' => $band,
            'setting' => $setting
        ]);
    }

    public function syncCalendar(Bands $band)
    {
        $calService = new CalendarService($band);
        $calService->syncEvents();
        $calService->syncBookings();

        return back()->with('successMessage', 'Events written to your calendar!');
    }

    public function createCalendar(CreateCalendarRequest $request, Bands $band)
    {
        $calService = new CalendarService($band, $request->type);
        $calendarId = $calService->createBandCalendarByType($request->type);
        
        if ($calendarId) {
            return back()->with('successMessage', 'Calendar created successfully! Calendar ID: ' . $calendarId);
        } else {
            return back()->withErrors(['Failed to create calendar. Please check the logs for more details.']);
        }
    }

    public function createCalendars(CreateCalendarRequest $request, Bands $band)
    {
        $calService = new CalendarService($band);
        $results = $calService->createAllBandCalendars();
        
        if (count($results) > 0) {
            // Automatically grant appropriate access to all band members
            foreach (['booking', 'events', 'public'] as $type) {
                $calService->grantBandAccessByType($type);
            }
            
            // Make public calendar publicly readable
            $calService->makePublicCalendarPublic();
            
            $message = 'Calendars created successfully and access granted! ';
            foreach ($results as $type => $calendarId) {
                $message .= ucfirst($type) . ': ' . $calendarId . ' ';
            }
            return back()->with('successMessage', $message);
        } else {
            return back()->withErrors(['Failed to create calendars. Please check the logs for more details.']);
        }
    }

    public function syncAllCalendars(Bands $band)
    {
        $types = ['booking', 'events', 'public'];
        $results = [];
        
        foreach ($types as $type) {
            $calService = new CalendarService($band, $type);
            $calService->syncEventsByType($type);
            $results[] = $type;
        }

        return back()->with('successMessage', 'All calendars synced: ' . implode(', ', $results));
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

        $band->save();

        return redirect()->route('bands')->with('successMessage', $band->name . ' was successfully updated');
    }
    public function deleteMember(Bands $band, User $user)
    {
        $author = Auth::user();

        if ($author->ownsBand($band->id))
        {
            $band->members()->where('user_id', $user->id)->delete();
            return redirect()->route('bands.editSettings', [$band->id, 'band members'])->with('successMessage', $user->name . ' removed from band members');
        }
        else
        {
            return back()->withErrors(['You are not authorized to remove members from this band.']);
        }
    }

    public function deleteOwner(Bands $band, $ownerParam)
    {
        $owner = BandOwners::where('user_id', '=', $ownerParam)->where('band_id', '=', $band->id)->first();
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
        \Stripe\Stripe::setApiKey(config('services.stripe.key'));

        $account = \Stripe\Account::create([
            'type' => 'standard',
        ]);

        $account_links = \Stripe\AccountLink::create([
            'account' => $account->id,
            'refresh_url' => url('/login'),
            'return_url' => url('/bands/' . $band->id . '/edit'),
            'type' => 'account_onboarding',
        ]);

        StripeAccounts::create([
            'band_id' => $band->id,
            'stripe_account_id' => $account->id,
            'status' => 'incomplete'
        ]);
        return \Redirect::away($account_links->url);
    }

    public function contacts(Bands $band)
    {
        // First load all the necessary relationships
        // Eager load what we need for the booking_history calculation
        $band->load([
            'contacts.bookingContacts' => function ($query)
            {
                $query->select('id', 'contact_id', 'booking_id');
            },
            'contacts.bookingContacts.booking' => function ($query)
            {
                $query->select('id', 'name', 'date');
            }
        ]);

        // Transform to only include what we need
        return $band->contacts->map(function ($contact)
        {
            return [
                'id' => $contact->id,
                'name' => $contact->name,
                'email' => $contact->email,
                'phone' => $contact->phone,
                'booking_history' => $contact->booking_history
            ];
        });
    }

    public function grantCalendarAccess(Request $request, Bands $band)
    {
        $request->validate([
            'email' => 'required|email',
            'role' => 'required|in:reader,writer,owner'
        ]);

        // Find the user by email
        $user = \App\Models\User::where('email', $request->email)->first();
        
        if (!$user) {
            return back()->withErrors(['User with email ' . $request->email . ' not found.']);
        }

        $calService = new CalendarService($band, $request->calendarType);
        
        $success = $calService->grantUserAccess($user, $request->role);
        
        if ($success) {
            return back()->with('successMessage', 'Calendar access granted to ' . $request->email);
        } else {
            return back()->withErrors(['Failed to grant calendar access. Please check the logs for more details.']);
        }
    }

    public function grantCalendarAccessByType(Request $request, Bands $band, $type)
    {
        $request->validate([
            'email' => 'required|email',
            'role' => 'required|in:reader,writer,owner',
        ]);

        $user = \App\Models\User::where('email', $request->email)->first();
        
        if (!$user) {
            return back()->withErrors(['User with email ' . $request->email . ' not found.']);
        }

        $calendar = $band->calendars()->where('type', $type)->first();
        if (!$calendar) {
            return back()->withErrors(['No ' . $type . ' calendar found for this band.']);
        }

        // Check if this access level is appropriate for the calendar type
        $calService = new CalendarService($band, $type);
        
        // Determine if user is owner or member of band
        $userRole = 'guest';
        if ($band->owners()->where('user_id', $user->id)->exists()) {
            $userRole = 'owner';
        } elseif ($band->members()->where('user_id', $user->id)->exists()) {
            $userRole = 'member';
        }

        // Get appropriate role for this calendar type
        $appropriateRole = $calService->getAccessRoleByType($type, $userRole);
        
        if (!$appropriateRole && $type === 'booking' && $userRole === 'member') {
            return back()->withErrors(['Members cannot access booking calendar. Only owners have access to bookings.']);
        }

        $service = GoogleCalendarFactory::createForCalendarId($calendar->calendar_id)->getService();
        $success = $calService->grantUserAccessToCalendar($service, $user, $request->role, $calendar->calendar_id);
        
        if ($success) {
            return back()->with('successMessage', ucfirst($type) . ' calendar access granted to ' . $request->email);
        } else {
            return back()->withErrors(['Failed to grant calendar access. Please check the logs for more details.']);
        }
    }

    public function syncBandCalendarAccess(Bands $band)
    {
        $calService = new CalendarService($band);
        $success = $calService->grantBandAccess();
        
        if ($success) {
            return back()->with('successMessage', 'All band members now have calendar access!');
        } else {
            return back()->withErrors(['Failed to sync band member access. Please check the logs for more details.']);
        }
    }
}
