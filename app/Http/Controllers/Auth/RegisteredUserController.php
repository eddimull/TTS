<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\BandMembers;
use App\Models\BandOwners;
use App\Models\User;
use App\Providers\RouteServiceProvider;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use App\Models\Invitations;
use App\Models\EventSubs;
use App\Models\BandSubInvitation;
use App\Services\SubInvitationService;

class RegisteredUserController extends Controller
{
    const OWNER_INVITE_TYPE = 1;
    const MEMBER_INVITE_TYPE = 2;

    /**
     * Display the registration view.
     *
     * @param  Request  $request
     * @return \Inertia\Response
     */
    public function create(Request $request)
    {
        $invitationEmail = null;
        $invitationName = null;

        // Check for sub invitation (event_subs)
        if ($request->filled('invitation')) {
            $eventSub = EventSubs::where('invitation_key', $request->invitation)
                ->where('pending', true)
                ->first();

            if ($eventSub) {
                $invitationEmail = $eventSub->email;
                $invitationName = $eventSub->name;
            }
            // Fall back to a band-level sub invitation with the same key
            else {
                $bandInvitation = BandSubInvitation::where('invitation_key', $request->invitation)
                    ->where('pending', true)
                    ->first();

                if ($bandInvitation) {
                    $invitationEmail = $bandInvitation->email;
                    $invitationName = $bandInvitation->name;
                }
            }
        }
        // Check for legacy invitation (band owner/member)
        elseif ($request->route('key')) {
            $invitationEmail = $this->getInvitationEmail($request->route('key'));
        }

        return Inertia::render('Auth/Register', [
            'invitationEmail' => $invitationEmail,
            'invitationName' => $invitationName,
        ]);
    }

    /**
     * Get the invitation email for a given key.
     *
     * @param  string  $key
     * @return string|null
     */
    private function getInvitationEmail(string $key): ?string
    {
        $invitation = Invitations::where('key', $key)
            ->where('pending', true)
            ->first();

        return $invitation ? $invitation->email : null;
    }

    /**
     * Handle an incoming registration request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|confirmed|min:8',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        // Handle sub invitations (event_subs)
        $subInvitations = EventSubs::where('email', $user->email)
            ->where('pending', true)
            ->get();

        if ($subInvitations->isNotEmpty()) {
            $subInvitationService = new SubInvitationService();

            foreach ($subInvitations as $eventSub) {
                // Accept each sub invitation
                $subInvitationService->acceptInvitation($eventSub->invitation_key, $user);
            }
        }

        // Handle band-level sub invitations (band_sub_invitations)
        $bandSubInvitations = BandSubInvitation::where('email', $user->email)
            ->where('pending', true)
            ->get();

        if ($bandSubInvitations->isNotEmpty()) {
            $subInvitationService = $subInvitationService ?? new SubInvitationService();

            foreach ($bandSubInvitations as $bandInvitation) {
                $subInvitationService->acceptBandInvitation($bandInvitation->invitation_key, $user);
            }
        }

        // Handle legacy band owner/member invitations
        $invitations = Invitations::where('email', $user->email)->where('pending', true)->get();

        foreach ($invitations as $invitation)
        {
            if ($invitation->invite_type_id === static::OWNER_INVITE_TYPE)
            {
                BandOwners::create([
                    'user_id' => $user->id,
                    'band_id' => $invitation->band_id
                ]);
                setPermissionsTeamId($invitation->band_id);
                $user->assignRole('band-owner');
                setPermissionsTeamId(null);
            }
            if ($invitation->invite_type_id === static::MEMBER_INVITE_TYPE)
            {
                BandMembers::create([
                    'user_id' => $user->id,
                    'band_id' => $invitation->band_id
                ]);
                $user->assignBandMemberDefaults($invitation->band_id);
            }

            $invitation->pending = false;
            $invitation->save();
        }
        event(new Registered($user));

        Auth::login($user);

        // Users who joined a band through a pending invitation go straight to
        // the dashboard. Everyone else needs to pick how to get started —
        // create a band, join one, or go solo (mirrors the mobile flow).
        if ($user->allBands()->isEmpty()) {
            return redirect()->route('onboarding');
        }

        return redirect(RouteServiceProvider::HOME);
    }
}
