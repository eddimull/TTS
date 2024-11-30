<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class PandaDocController extends Controller
{
    public function initiateAuth()
    {
        $query = http_build_query([
            'client_id' => config('services.pandadoc.client_id'),
            'redirect_uri' => 'https://local.tts.band:8710/auth/pandadoc/callback',
            'response_type' => 'code'
        ]);

        return redirect('https://app.pandadoc.com/oauth2/authorize?' . $query);
    }

    public function handleCallback(Request $request)
    {
        $response = Http::asForm()->post('https://api.pandadoc.com/oauth2/access_token', [
            'grant_type' => 'authorization_code',
            'client_id' => config('services.pandadoc.client_id'),
            'client_secret' => config('services.pandadoc.client_secret'),
            'code' => $request->code,
            'redirect_uri' => 'https://local.tts.band:8710/auth/pandadoc/callback',
        ]);

        if ($response->successful())
        {
            $accessToken = $response->json()['access_token'];
            $refreshToken = $response->json()['refresh_token'];

            // Store these tokens securely
            // This is a placeholder - implement secure storage in your application
            session(['pandadoc_access_token' => $accessToken]);
            session(['pandadoc_refresh_token' => $refreshToken]);

            return "Authentication successful! Tokens have been stored in the session. Access token: $accessToken, Refresh token: $refreshToken";
        }
        else
        {
            return "Authentication failed: " . $response->body();
        }
    }
}
