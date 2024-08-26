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
        // If the request has a key, we will use it to get the invitation email
        // This is used to pre-fill the email field in the registration form
        // so the registration flow can be reused for both invites and regular registrations
        if ($request->filled('key'))
        {
            $invitationEmail = $this->getInvitationEmail($request->key);
        }

        return Inertia::render('Auth/Register', [
            'invitationEmail' => $invitationEmail
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


        $invitations = Invitations::where('email', $user->email)->where('pending', true)->get();

        foreach ($invitations as $invitation)
        {
            if ($invitation->invite_type_id === static::OWNER_INVITE_TYPE)
            {
                BandOwners::create([
                    'user_id' => $user->id,
                    'band_id' => $invitation->band_id
                ]);
            }
            if ($invitation->invite_type_id == static::MEMBER_INVITE_TYPE)
            {
                BandMembers::create([
                    'user_id' => $user->id,
                    'band_id' => $invitation->band_id
                ]);
            }

            $invitation->pending = false;
            $invitation->save();
        }
        event(new Registered($user));

        Auth::login($user);

        return redirect(RouteServiceProvider::HOME);
    }
}
