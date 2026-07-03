<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\BandMembers;
use App\Models\BandOwners;
use App\Models\Bands;
use App\Models\Invitations;
use App\Services\InvitationServices;
use App\Services\Mobile\TokenService;
use App\Services\PendingInvitationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use App\Models\User;

class OnboardingController extends Controller
{
    const OWNER_INVITE_TYPE = 1;
    const MEMBER_INVITE_TYPE = 2;

    public function __construct(
        private readonly TokenService $tokenService,
        private readonly PendingInvitationService $pendingInvitations,
    ) {}

    // ── POST /api/mobile/auth/register ────────────────────────────────────────

    public function register(Request $request): JsonResponse
    {
        $request->validate([
            'name'                  => 'required|string|max:255',
            'email'                 => 'required|string|email|max:255|unique:users',
            'password'              => 'required|string|min:8|confirmed',
            'device_name'           => 'required|string|max:255',
        ]);

        $user = User::create([
            'name'     => $request->name,
            'email'    => $request->email,
            'password' => Hash::make($request->password),
        ]);

        $this->pendingInvitations->applyFor($user);

        $abilities = $this->tokenService->buildAbilities($user);
        $token     = $user->createToken($request->device_name, $abilities)->plainTextToken;

        return response()->json([
            'token' => $token,
            'user'  => $this->tokenService->formatUser($user),
            'bands' => $this->tokenService->formatBands($user),
        ], 201);
    }

    // ── POST /api/mobile/bands ────────────────────────────────────────────────

    public function createBand(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $user = $request->user();

        $siteName = $this->uniqueSiteName(Str::slug($request->name));

        $band = Bands::create([
            'name'      => $request->name,
            'site_name' => $siteName,
        ]);

        BandOwners::create([
            'user_id' => $user->id,
            'band_id' => $band->id,
        ]);

        setPermissionsTeamId($band->id);
        $user->assignRole('band-owner');
        setPermissionsTeamId(null);

        return response()->json([
            'band' => [
                'id'       => $band->id,
                'name'     => $band->name,
                'is_owner' => true,
            ],
        ], 201);
    }

    // ── POST /api/mobile/bands/{band}/invite ──────────────────────────────────

    public function inviteMembers(Request $request, Bands $band): JsonResponse
    {
        $request->validate([
            'emails'   => 'required|array|min:1|max:50',
            'emails.*' => 'required|email',
        ]);

        $user = $request->user();

        if (!$user->ownsBand($band->id)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        // Temporarily set auth so InvitationServices can read Auth::user()
        Auth::setUser($user);

        $service = new InvitationServices();
        foreach ($request->emails as $email) {
            $existingUser = \App\Models\User::where('email', $email)->first();
            if ($existingUser && ($existingUser->ownsBand($band->id) || $existingUser->bandMember->contains('id', $band->id))) {
                continue; // Already a member — skip silently
            }
            $service->inviteUser($email, $band->id, false);
        }

        return response()->json(['message' => 'Invitations sent.']);
    }

    // ── POST /api/mobile/bands/join ───────────────────────────────────────────

    public function joinBand(Request $request): JsonResponse
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

        // Reusable QR invitations have no email and stay pending so the same key can be scanned by multiple members.
        if ($invitation->email !== null) {
            $invitation->pending = false;
            $invitation->save();
        }

        return response()->json([
            'bands' => $this->tokenService->formatBands($user),
        ]);
    }

    // ── GET /api/mobile/bands/{band}/invite-qr ────────────────────────────────

    public function inviteQr(Request $request, Bands $band): JsonResponse
    {
        $user = $request->user();

        if (!$user->ownsBand($band->id)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        // Get or create a pending member invitation for this band
        $invitation = Invitations::firstOrCreate(
            [
                'band_id'        => $band->id,
                'invite_type_id' => static::MEMBER_INVITE_TYPE,
                'pending'        => true,
                'email'          => null,
            ]
        );

        return response()->json(['key' => $invitation->key]);
    }

    // ── POST /api/mobile/bands/solo ───────────────────────────────────────────

    public function goSolo(Request $request): JsonResponse
    {
        $user = $request->user();

        // Idempotency: return existing personal band if one already exists
        $existing = Bands::whereHas('owners', fn ($q) => $q->where('user_id', $user->id))
            ->where('is_personal', true)
            ->first();

        if ($existing) {
            return response()->json([
                'token' => $this->tokenService->reissueForCurrentDevice(
                    $user,
                    $user->currentAccessToken(),
                ),
                'bands' => $this->tokenService->formatBands($user),
            ]);
        }

        $band = $this->createPersonalBand($user);

        // A concurrent solo request for this same user won the race and already
        // created the personal band; return that idempotent result.
        if ($band === null) {
            $user->unsetRelation('bandOwner')
                ->unsetRelation('bandMember')
                ->unsetRelation('bandSub');

            return response()->json([
                'token' => $this->tokenService->reissueForCurrentDevice(
                    $user,
                    $user->currentAccessToken(),
                ),
                'bands' => $this->tokenService->formatBands($user),
            ]);
        }

        BandOwners::create([
            'user_id' => $user->id,
            'band_id' => $band->id,
        ]);

        setPermissionsTeamId($band->id);
        $user->assignRole('band-owner');
        setPermissionsTeamId(null);

        // buildAbilities() (via allBands()) reads the bandOwner, bandMember, and
        // bandSub relations. Clear all three cached relations so the freshly
        // reissued token reflects the newly created BandOwners row — not a stale
        // pre-creation cache. (Only bandOwner changed here, but clearing all
        // three keeps this correct if the flow ever assigns the others too.)
        $user->unsetRelation('bandOwner')
            ->unsetRelation('bandMember')
            ->unsetRelation('bandSub');

        return response()->json([
            'token' => $this->tokenService->reissueForCurrentDevice(
                $user,
                $user->currentAccessToken(),
            ),
            'bands' => $this->tokenService->formatBands($user),
        ], 201);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Create the user's personal band, tolerating the site_name unique index.
     *
     * site_name is generated and only checked (not locked) before insert, so a
     * concurrent request can claim the same slug in between. On a unique
     * violation we distinguish the cause:
     *   - this user already got a personal band (same-user double-submit) →
     *     return null so the caller returns the idempotent result;
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
            } catch (\Illuminate\Database\QueryException $e) {
                if (!str_starts_with((string) $e->getCode(), '23')) {
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
}
