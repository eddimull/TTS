<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Services\Mobile\TokenService;
use App\Services\SocialAuth\InvalidSocialTokenException;
use App\Services\SocialAuth\SocialAuthService;
use App\Services\SocialAuth\SocialTokenVerifierManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SocialAuthController extends Controller
{
    public function __construct(
        private readonly TokenService $tokenService,
        private readonly SocialTokenVerifierManager $verifiers,
        private readonly SocialAuthService $socialAuth,
    ) {}

    public function token(Request $request): JsonResponse
    {
        $data = $request->validate([
            'provider'    => 'required|string|in:google,apple,facebook',
            'token'       => 'required|string',
            'device_name' => 'required|string|max:255',
        ]);

        try {
            $profile = $this->verifiers->for($data['provider'])->verify($data['token']);
        } catch (InvalidSocialTokenException $e) {
            throw ValidationException::withMessages(['token' => [$e->getMessage()]]);
        }

        $user = $this->socialAuth->resolveUser($profile);

        $abilities = $this->tokenService->buildAbilities($user);
        $token     = $user->createToken($data['device_name'], $abilities)->plainTextToken;

        return response()->json([
            'token' => $token,
            'user'  => $this->tokenService->formatUser($user),
            'bands' => $this->tokenService->formatBands($user),
        ]);
    }
}
