<?php

namespace App\Http\Controllers\Onboarding;

use App\Http\Controllers\Controller;
use App\Models\BandMembers;
use App\Models\BandOwners;
use App\Models\Bands;
use App\Models\Invitations;
use App\Models\User;
use App\Providers\RouteServiceProvider;
use Illuminate\Database\QueryException;
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

        // Email-addressed invitations are targeted at a specific person, so
        // only that person may consume them — a leaked/forwarded code must not
        // let someone else join in their place. Null-email invitations are the
        // reusable QR case and remain open to any authenticated user.
        if ($invitation->email !== null
            && strcasecmp($invitation->email, $user->email) !== 0) {
            throw ValidationException::withMessages([
                'key' => ['This invite code was issued to a different email address.'],
            ]);
        }

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

        $band = $this->createPersonalBand($user);

        // A concurrent solo request for this same user won the race and already
        // created the personal band; that's the idempotent outcome.
        if ($band === null) {
            return redirect(RouteServiceProvider::HOME);
        }

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
     * Create the user's personal band, tolerating the site_name unique index.
     *
     * Because site_name is generated and only checked (not locked) before the
     * insert, a concurrent request can claim the same slug in between. On a
     * unique violation we distinguish the two causes:
     *   - this user already got a personal band (a same-user double-submit) →
     *     return null so the caller treats it as the idempotent case;
     *   - a *different* band claimed the slug → regenerate and retry.
     *
     * Returns the created band, or null if the user already has one.
     */
    private function createPersonalBand(User $user): ?Bands
    {
        $name = "{$user->name}'s Band";

        for ($attempt = 0; $attempt < 5; $attempt++) {
            try {
                return Bands::create([
                    'name'        => $name,
                    'site_name'   => $this->uniqueSiteName(Str::slug($name)),
                    'is_personal' => true,
                ]);
            } catch (QueryException $e) {
                if (!$this->isUniqueViolation($e)) {
                    throw $e;
                }

                $existing = Bands::whereHas('owners', fn ($q) => $q->where('user_id', $user->id))
                    ->where('is_personal', true)
                    ->first();

                if ($existing) {
                    return null;
                }
                // Otherwise a different band took the slug — loop and retry.
            }
        }

        throw new \RuntimeException('Unable to allocate a unique site_name for the personal band.');
    }

    /**
     * Generate a site_name slug that doesn't collide with an existing band.
     */
    private function uniqueSiteName(string $base): string
    {
        // Normalise to a non-empty base up front so the suffixed candidates
        // below build on it too (e.g. 'band-1', not '-1', when $base is empty).
        $base      = $base ?: 'band';
        $candidate = $base;
        $suffix    = 1;

        while (Bands::where('site_name', $candidate)->exists()) {
            $candidate = "{$base}-{$suffix}";
            $suffix++;
        }

        return $candidate;
    }

    /**
     * Whether a QueryException is an integrity/unique-constraint violation
     * (SQLSTATE 23xxx), independent of the database driver.
     */
    private function isUniqueViolation(QueryException $e): bool
    {
        return str_starts_with((string) $e->getCode(), '23');
    }
}
