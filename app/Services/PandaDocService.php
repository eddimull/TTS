<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PandaDocService
{
    private $clientId;
    private $clientSecret;
    private $accessToken;
    private $refreshToken;
    private $tokenExpiresAt;

    public function __construct()
    {
        $this->clientId = config('services.pandadoc.client_id');
        $this->clientSecret = config('services.pandadoc.client_secret');
        $this->accessToken = config('services.pandadoc.access_token');
        $this->refreshToken = config('services.pandadoc.refresh_token');
        $this->tokenExpiresAt = config('services.pandadoc.token_expires_at');
    }

    public function getValidAccessToken()
    {
        // Check if current token is still valid (with 5-minute buffer)
        if ($this->tokenExpiresAt && time() < ($this->tokenExpiresAt - 300)) {
            return $this->accessToken;
        }

        // Token is expired or about to expire, refresh it
        return $this->refreshAccessToken();
    }

    public function refreshAccessToken()
    {
        try {
            $response = Http::asForm()->post('https://api.pandadoc.com/oauth2/access_token', [
                'grant_type' => 'refresh_token',
                'refresh_token' => $this->refreshToken,
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
            ]);

            if (!$response->successful()) {
                Log::error('Failed to refresh PandaDoc token: ' . $response->body());
                throw new \Exception('Failed to refresh PandaDoc access token');
            }

            $tokenData = $response->json();
            
            // Update the tokens
            $this->accessToken = $tokenData['access_token'];
            $this->refreshToken = $tokenData['refresh_token'] ?? $this->refreshToken;
            $this->tokenExpiresAt = time() + $tokenData['expires_in'];

            // Store the new tokens (you might want to store these in database instead)
            $this->updateEnvFile([
                'PANDADOC_ACCESS_TOKEN' => $this->accessToken,
                'PANDADOC_REFRESH_TOKEN' => $this->refreshToken,
                'PANDADOC_TOKEN_EXPIRES_AT' => $this->tokenExpiresAt,
            ]);

            Log::info('PandaDoc access token refreshed successfully');
            
            return $this->accessToken;

        } catch (\Exception $e) {
            Log::error('Exception while refreshing PandaDoc token: ' . $e->getMessage());
            throw $e;
        }
    }

    private function updateEnvFile($data)
    {
        $envFile = base_path('.env');
        $envContent = file_get_contents($envFile);

        foreach ($data as $key => $value) {
            $pattern = "/^{$key}=.*/m";
            $replacement = "{$key}={$value}";
            
            if (preg_match($pattern, $envContent)) {
                $envContent = preg_replace($pattern, $replacement, $envContent);
            } else {
                $envContent .= "\n{$replacement}";
            }
        }

        file_put_contents($envFile, $envContent);
    }

    public function makeAuthenticatedRequest($method, $url, $data = [])
    {
        $accessToken = $this->getValidAccessToken();
        Log::info("Making {$method} request to {$url} with access token: " . substr($accessToken, 0, 20) . '...');
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $accessToken,
            'Content-Type' => 'application/json',
        ])->{$method}($url, $data);

        // If we get a 401, try refreshing the token once
        if ($response->status() === 401) {
            Log::info('Got 401, attempting to refresh token and retry');
            $accessToken = $this->refreshAccessToken();
            
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $accessToken,
                'Content-Type' => 'application/json',
            ])->{$method}($url, $data);
        }

        return $response;
    }
}