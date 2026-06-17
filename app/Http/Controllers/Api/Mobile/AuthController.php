<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Requests\Mobile\TokenRequest;
use App\Mail\AccountDeletionConfirmation;
use App\Models\Country;
use App\Models\State;
use App\Models\User;
use App\Services\AccountDeletionService;
use App\Services\Mobile\TokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(
        private readonly TokenService $tokenService,
    ) {}

    public function token(TokenRequest $request): JsonResponse
    {
        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $abilities = $this->tokenService->buildAbilities($user);
        $token     = $user->createToken($request->device_name, $abilities)->plainTextToken;

        return response()->json([
            'token' => $token,
            'user'  => $this->tokenService->formatUser($user),
            'bands' => $this->tokenService->formatBands($user),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'user'  => $this->tokenService->formatUser($user),
            'bands' => $this->tokenService->formatBands($user),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully.']);
    }

    public function refresh(Request $request): JsonResponse
    {
        $user  = $request->user();
        // Under auth:sanctum on this non-stateful mobile route, currentAccessToken()
        // is always a PersonalAccessToken (bearer auth). A TransientToken only
        // arises for session/cookie auth, which never reaches this endpoint.
        $token = $this->tokenService->reissueForCurrentDevice(
            $user,
            $user->currentAccessToken(),
        );

        return response()->json([
            'token' => $token,
            'user'  => $this->tokenService->formatUser($user),
            'bands' => $this->tokenService->formatBands($user),
        ]);
    }

    // ── Account management ────────────────────────────────────────────────────

    /**
     * GET /api/mobile/account — full editable profile plus the state/country
     * lookup lists the Flutter pickers need (mirrors the web Account page props).
     */
    public function showAccount(Request $request): JsonResponse
    {
        return response()->json([
            'account'   => $this->tokenService->formatAccount($request->user()),
            'states'    => State::orderBy('state_name')
                ->get(['state_id', 'state_name', 'country_id']),
            'countries' => Country::orderBy('country_name')
                ->get(['id', 'country_name']),
        ]);
    }

    /**
     * PATCH /api/mobile/account — update the profile. Mirrors the web
     * AccountController::update: password is only changed when provided.
     *
     * Full-replace semantics (by design): the client always submits the entire
     * form, so an omitted optional field (address/city/state/country/zip) is
     * treated as "cleared" and set to null — it is NOT a partial patch. The one
     * exception is `password`, which is left untouched unless a new value is
     * sent. See test_omitting_optional_fields_clears_them for the contract.
     */
    public function updateAccount(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'name'                => 'required|string|max:255',
            'email'               => 'required|email|max:255|unique:users,email,' . $user->id,
            'password'            => 'nullable|string|min:8|confirmed',
            'address1'            => 'nullable|string|max:255',
            'address2'            => 'nullable|string|max:255',
            'city'                => 'nullable|string|max:255',
            // state_id/country_id come from the lookup lists as numeric IDs, so
            // accept integers (or numeric strings) only — `integer` rejects
            // arrays/objects that would otherwise cast to the string "Array"
            // and corrupt the profile. Stored as varchar, so cast to string below.
            'state_id'            => 'nullable|integer',
            'country_id'          => 'nullable|integer',
            // Match the band-settings address fields (ZIP+4 / international).
            'zip'                 => 'nullable|string|max:20',
            'email_notifications' => 'required|boolean',
        ]);

        $user->name               = $data['name'];
        $user->email              = $data['email'];
        $user->Address1           = $data['address1'] ?? null;
        $user->Address2           = $data['address2'] ?? null;
        $user->City               = $data['city'] ?? null;
        $user->StateID            = isset($data['state_id']) ? (string) $data['state_id'] : null;
        $user->CountryID          = isset($data['country_id']) ? (string) $data['country_id'] : null;
        $user->Zip                = $data['zip'] ?? null;
        $user->emailNotifications = $data['email_notifications'];

        if (!empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }

        $user->save();

        return response()->json(['account' => $this->tokenService->formatAccount($user)]);
    }

    /**
     * DELETE /api/mobile/account — request deletion. Emails the user a signed,
     * expiring confirmation link rather than deleting immediately. The account
     * is only removed when that link is opened (confirmDeletion).
     */
    public function requestDeletion(Request $request): JsonResponse
    {
        $user = $request->user();

        // Emails a signed link to the neutral, shared confirmation page
        // (account.confirm-deletion) — the same page the web flow uses. The page
        // POSTs back to actually delete, so a GET prefetch can never trigger it.
        Mail::to($user->email)->send(
            new AccountDeletionConfirmation($user, AccountDeletionService::confirmationUrl($user))
        );

        return response()->json([
            'message' => 'Check your email to confirm account deletion. The link expires in 60 minutes.',
        ], 202);
    }
}
