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
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(
        private readonly TokenService $tokenService,
        private readonly AccountDeletionService $accountDeletionService,
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
            'state_id'            => 'nullable|string|max:255',
            'country_id'          => 'nullable|string|max:255',
            'zip'                 => 'nullable|string|max:5',
            'email_notifications' => 'required|boolean',
        ]);

        $user->name               = $data['name'];
        $user->email              = $data['email'];
        $user->Address1           = $data['address1'] ?? null;
        $user->Address2           = $data['address2'] ?? null;
        $user->City               = $data['city'] ?? null;
        $user->StateID            = $data['state_id'] ?? null;
        $user->CountryID          = $data['country_id'] ?? null;
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

        $url = URL::temporarySignedRoute(
            'mobile.account.confirm-deletion',
            now()->addMinutes(60),
            ['user' => $user->id],
        );

        Mail::to($user->email)->send(new AccountDeletionConfirmation($user, $url));

        return response()->json([
            'message' => 'Check your email to confirm account deletion. The link expires in 60 minutes.',
        ], 202);
    }

    /**
     * GET /api/mobile/account/confirm-deletion/{user} — signed link target.
     * Public (no token): the signature is the credential, since the user may
     * open this from their mail client. Runs the deletion and returns a simple
     * web confirmation page.
     *
     * The route param is taken as a raw int (not route-model-bound) so the
     * signature is validated BEFORE any DB lookup — this avoids a DB hit on
     * forged links and prevents leaking account existence via a 403-vs-404
     * difference (an invalid signature always 403s, regardless of the id).
     */
    public function confirmDeletion(Request $request, int $user)
    {
        abort_unless($request->hasValidSignature(), 403, 'This deletion link is invalid or has expired.');

        $account = User::findOrFail($user);

        $this->accountDeletionService->deleteAccount($account);

        return response()->view('account.deletion-confirmed');
    }
}
