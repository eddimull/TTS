<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\BandApiToken;

class AuthenticateBandApiToken
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (!$token) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'API token is required'
            ], 401);
        }

        // Hash the token to match against database
        $hashedToken = hash('sha256', $token);

        // Find the token in database
        $apiToken = BandApiToken::where('token', $hashedToken)
            ->where('is_active', true)
            ->with('band')
            ->first();

        if (!$apiToken) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'Invalid or inactive API token'
            ], 401);
        }

        // Mark token as used (run in background to avoid slowing down response)
        dispatch(function () use ($apiToken) {
            $apiToken->markAsUsed();
        })->afterResponse();

        // Attach the band and api_token to the request
        $request->merge([
            'authenticated_band' => $apiToken->band,
            'api_token' => $apiToken,
        ]);

        return $next($request);
    }
}
