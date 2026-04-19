<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\BandMembers;
use App\Models\BandOwners;
use App\Models\Bands;
use App\Models\Invitations;
use App\Services\InvitationServices;
use App\Services\Mobile\TokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use App\Models\User;
use App\Models\EventSubs;
use App\Services\SubInvitationService;

class OnboardingController extends Controller
{
    const OWNER_INVITE_TYPE = 1;
    const MEMBER_INVITE_TYPE = 2;

    public function __construct(private readonly TokenService $tokenService) {}

    // ── POST /api/mobile/auth/register ────────────────────────────────────────

    public function register(Request $request): JsonResponse
    {
        $request->validate([
            'name'                  => 'required|string|max:255',
            'email'                 => 'required|string|email|max:255|unique:users',
            'password'              => 'required|string|min:8|confirmed',
            'device_name'           => 'required|string',
        ]);

        $user = User::create([
            'name'     => $request->name,
            'email'    => $request->email,
            'password' => Hash::make($request->password),
        ]);

        // Apply any pending sub-invitations
        $subInvitations = EventSubs::where('email', $user->email)
            ->where('pending', true)
            ->get();

        if ($subInvitations->isNotEmpty()) {
            $service = new SubInvitationService();
            foreach ($subInvitations as $eventSub) {
                $service->acceptInvitation($eventSub->invitation_key, $user);
            }
        }

        // Apply any pending band invitations
        $invitations = Invitations::where('email', $user->email)
            ->where('pending', true)
            ->get();

        foreach ($invitations as $invitation) {
            if ($invitation->invite_type_id === static::OWNER_INVITE_TYPE) {
                BandOwners::create([
                    'user_id' => $user->id,
                    'band_id' => $invitation->band_id,
                ]);
                setPermissionsTeamId($invitation->band_id);
                $user->assignRole('band-owner');
                setPermissionsTeamId(null);
            }
            if ($invitation->invite_type_id === static::MEMBER_INVITE_TYPE) {
                BandMembers::create([
                    'user_id' => $user->id,
                    'band_id' => $invitation->band_id,
                ]);
                $user->assignBandMemberDefaults($invitation->band_id);
            }
            $invitation->pending = false;
            $invitation->save();
        }

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
            'emails'   => 'required|array|min:1',
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

        $invitation->pending = false;
        $invitation->save();

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
        $invitation = Invitations::where('band_id', $band->id)
            ->where('invite_type_id', static::MEMBER_INVITE_TYPE)
            ->where('pending', true)
            ->whereNull('email') // reusable invite has no email
            ->first();

        if (!$invitation) {
            $invitation = Invitations::create([
                'email'          => null,
                'band_id'        => $band->id,
                'invite_type_id' => static::MEMBER_INVITE_TYPE,
            ]);
        }

        return response()->json(['key' => $invitation->key]);
    }

    // ── POST /api/mobile/bands/solo ───────────────────────────────────────────

    public function goSolo(Request $request): JsonResponse
    {
        $user = $request->user();

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

        return response()->json([
            'bands' => $this->tokenService->formatBands($user),
        ], 201);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

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
