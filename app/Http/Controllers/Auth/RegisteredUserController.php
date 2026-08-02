<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
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
use App\Services\PendingInvitationService;

class RegisteredUserController extends Controller
{
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

        // Consume pending invitations (sub, band-level sub, owner/member) and
        // link any pre-registration assignments — shared with the mobile and
        // social sign-up paths so the three cannot drift.
        app(PendingInvitationService::class)->applyFor($user);

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
