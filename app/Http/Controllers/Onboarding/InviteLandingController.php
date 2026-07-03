<?php

namespace App\Http\Controllers\Onboarding;

use App\Http\Controllers\Controller;
use App\Models\Bands;
use App\Models\Invitations;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Public landing page for the invite QR URL (https://tts.band/invite/{key}).
 *
 * Phones with the app installed never reach this route — Android App Links /
 * iOS Universal Links open the app directly (see
 * public/.well-known/assetlinks.json and apple-app-site-association).
 * Browsers land here: the page offers the mobile app and, for the web, a
 * join path that survives login/registration via the session.
 */
class InviteLandingController extends Controller
{
    public function show(Request $request, string $key): Response
    {
        $invitation = Invitations::where('key', $key)
            ->where('pending', true)
            ->first();

        $band = $invitation ? Bands::find($invitation->band_id) : null;

        if ($invitation) {
            // Remember the key so a guest who registers gets it prefilled on
            // the onboarding join form, and send post-login traffic straight
            // back to this page.
            $request->session()->put('pending_invite_key', $key);
            redirect()->setIntendedUrl(route('invite.landing', $key));
        }

        return Inertia::render('Onboarding/InviteLanding', [
            'inviteKey' => $key,
            'valid' => $invitation !== null,
            'bandName' => $band?->name,
            'appStoreUrl' => config('services.mobile_app.app_store_url'),
            'playStoreUrl' => config('services.mobile_app.play_store_url'),
        ]);
    }
}
