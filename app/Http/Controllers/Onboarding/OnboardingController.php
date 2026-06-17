<?php

namespace App\Http\Controllers\Onboarding;

use App\Http\Controllers\Controller;
use App\Models\BandMembers;
use App\Models\BandOwners;
use App\Models\Bands;
use App\Models\Invitations;
use App\Providers\RouteServiceProvider;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Session-based onboarding for brand-new web users with no band yet.
 *
 * Mirrors the mobile API onboarding flow
 * (App\Http\Controllers\Api\Mobile\OnboardingController): after registering,
 * the user picks how to get a band — create one, join via invite code, or go
 * solo with a personal band. "Create a band" reuses the existing
 * bands.create / bands.store routes.
 */
class OnboardingController extends Controller
{
    const OWNER_INVITE_TYPE = 1;
    const MEMBER_INVITE_TYPE = 2;

    /**
     * Display the path-selection screen. This is an empty-state-only page:
     * users who already belong to a band are sent straight to the dashboard.
     */
    public function show(Request $request): Response|RedirectResponse
    {
        if ($request->user()->allBands()->isNotEmpty()) {
            return redirect(RouteServiceProvider::HOME);
        }

        return Inertia::render('Onboarding/PathSelection');
    }

    /**
     * Join an existing band with an invite code. Mirrors the mobile
     * joinBand endpoint, including the reusable-QR rule: invitations with a
     * null email stay pending so the same key can be used by multiple members.
     */
    public function join(Request $request): RedirectResponse
    {
        $request->validate([
            'key' => 'required|string',
        ]);

        $invitation = Invitations::where('key', $request->key)
            ->where('pending', true)
            ->first();

        if (!$invitation) {
            throw ValidationException::withMessages([
                'key' => ['Invalid or expired invite code.'],
            ]);
        }

        $user = $request->user();

        if ($invitation->invite_type_id === static::OWNER_INVITE_TYPE) {
            BandOwners::firstOrCreate([
                'user_id' => $user->id,
                'band_id' => $invitation->band_id,
            ]);
            setPermissionsTeamId($invitation->band_id);
            $user->assignRole('band-owner');
            setPermissionsTeamId(null);
        } else {
            BandMembers::firstOrCreate([
                'user_id' => $user->id,
                'band_id' => $invitation->band_id,
            ]);
            $user->assignBandMemberDefaults($invitation->band_id);
        }

        // Reusable QR invitations have no email and stay pending so the same
        // key can be scanned/entered by multiple members.
        if ($invitation->email !== null) {
            $invitation->pending = false;
            $invitation->save();
        }

        return redirect(RouteServiceProvider::HOME)
            ->with('successMessage', 'You\'ve joined the band!');
    }

    /**
     * Create a personal band for solo use. Mirrors the mobile goSolo
     * endpoint and is idempotent: if the user already has a personal band,
     * just route them to the dashboard.
     */
    public function solo(Request $request): RedirectResponse
    {
        $user = $request->user();

        $existing = Bands::whereHas('owners', fn ($q) => $q->where('user_id', $user->id))
            ->where('is_personal', true)
            ->first();

        if ($existing) {
            return redirect(RouteServiceProvider::HOME);
        }

        $name     = "{$user->name}'s Band";
        $siteName = $this->uniqueSiteName(Str::slug($name));

        $band = Bands::create([
            'name'        => $name,
            'site_name'   => $siteName,
            'is_personal' => true,
        ]);

        BandOwners::create([
            'user_id' => $user->id,
            'band_id' => $band->id,
        ]);

        setPermissionsTeamId($band->id);
        $user->assignRole('band-owner');
        setPermissionsTeamId(null);

        return redirect(RouteServiceProvider::HOME)
            ->with('successMessage', 'Your personal band is ready!');
    }

    /**
     * Generate a site_name slug that doesn't collide with an existing band.
     */
    private function uniqueSiteName(string $base): string
    {
        $candidate = $base ?: 'band';
        $suffix    = 1;

        while (Bands::where('site_name', $candidate)->exists()) {
            $candidate = "{$base}-{$suffix}";
            $suffix++;
        }

        return $candidate;
    }
}
