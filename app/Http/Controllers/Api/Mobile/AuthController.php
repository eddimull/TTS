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
            // state_id/country_id come from the lookup lists as numeric IDs, so
            // accept integers (or numeric strings). They are stored as varchar
            // on the users table, so cast to string below.
            'state_id'            => 'nullable',
            'country_id'          => 'nullable',
            'zip'                 => 'nullable|string|max:5',
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

        // Signed URL points at the GET confirmation PAGE (not the deleting
        // action). The page posts back to the same signed URL to actually
        // delete — so a link prefetch/scanner (which only issues GETs) can
        // never trigger the irreversible delete.
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
     *
     * Renders a confirmation page with a button that POSTs back to the same
     * signed URL. It does NOT delete: GET is safe to prefetch (email security
     * scanners and "safe link" crawlers issue GETs), so the destructive action
     * lives on POST only.
     *
     * The route param is a raw int (not route-model-bound) so the signature is
     * validated BEFORE any DB lookup — no DB hit on forged links, and no
     * account-existence leak via 403-vs-404 (invalid signature always 403s).
     */
    public function confirmDeletion(Request $request, int $user): \Illuminate\Http\Response
    {
        abort_unless($request->hasValidSignature(), 403, 'This deletion link is invalid or has expired.');

        // Re-present the exact signed query string so the form can POST to the
        // same URL and pass signature validation again.
        return response()->view('account.confirm-deletion', [
            'actionUrl' => $request->fullUrl(),
        ]);
    }

    /**
     * POST /api/mobile/account/confirm-deletion/{user} — performs the deletion.
     * Same signed URL as the GET page; the signature is the credential. Only a
     * deliberate form submit (POST) reaches here, never a passive GET prefetch.
     */
    public function performDeletion(Request $request, int $user): \Illuminate\Http\Response
    {
        abort_unless($request->hasValidSignature(), 403, 'This deletion link is invalid or has expired.');

        $account = User::findOrFail($user);

        app(AccountDeletionService::class)->deleteAccount($account);

        return response()->view('account.deletion-confirmed');
    }
}
