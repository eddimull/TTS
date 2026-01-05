<?php

namespace App\Services;

use App\Models\GoogleDriveConnection;
use Google\Client;
use Google\Service\Drive;
use Google\Service\Oauth2;

class GoogleDriveOAuthService
{
    protected Client $client;

    public function __construct()
    {
        $this->client = new Client();
        $this->client->setClientId(config('services.google_drive.client_id'));
        $this->client->setClientSecret(config('services.google_drive.client_secret'));
        $this->client->setRedirectUri(config('services.google_drive.redirect_uri'));
        $this->client->addScope(Drive::DRIVE_READONLY);
        $this->client->addScope(Drive::DRIVE_METADATA_READONLY);
        $this->client->addScope('https://www.googleapis.com/auth/userinfo.email');
        $this->client->addScope('https://www.googleapis.com/auth/userinfo.profile');
        $this->client->setAccessType('offline');
        $this->client->setPrompt('consent'); // Force to get refresh token
        $this->client->setIncludeGrantedScopes(true);
    }

    /**
     * Generate OAuth authorization URL
     *
     * @param int $userId
     * @param int $bandId
     * @return string
     */
    public function getAuthorizationUrl(int $userId, int $bandId): string
    {
        $state = base64_encode(json_encode([
            'user_id' => $userId,
            'band_id' => $bandId,
            'timestamp' => now()->timestamp,
        ]));

        $this->client->setState($state);
        return $this->client->createAuthUrl();
    }

    /**
     * Exchange authorization code for tokens
     *
     * @param string $code
     * @return array
     * @throws \Exception
     */
    public function handleCallback(string $code): array
    {
        $token = $this->client->fetchAccessTokenWithAuthCode($code);

        if (isset($token['error'])) {
            throw new \Exception('OAuth error: ' . ($token['error_description'] ?? $token['error']));
        }

        // Get user info to store email
        $this->client->setAccessToken($token);
        $oauth2 = new Oauth2($this->client);
        $userInfo = $oauth2->userinfo->get();

        return [
            'access_token' => $token['access_token'],
            'refresh_token' => $token['refresh_token'] ?? null,
            'expires_in' => $token['expires_in'],
            'email' => $userInfo->email,
        ];
    }

    /**
     * Refresh access token
     *
     * @param GoogleDriveConnection $connection
     * @return void
     * @throws \Exception
     */
    public function refreshToken(GoogleDriveConnection $connection): void
    {
        if (!$connection->refresh_token) {
            throw new \Exception('No refresh token available for this connection');
        }

        $token = $this->client->fetchAccessTokenWithRefreshToken($connection->refresh_token);

        if (isset($token['error'])) {
            throw new \Exception('Token refresh failed: ' . ($token['error_description'] ?? $token['error']));
        }

        $connection->update([
            'access_token' => $token['access_token'],
            'token_expires_at' => now()->addSeconds($token['expires_in']),
        ]);
    }

    /**
     * Get authenticated Drive client
     *
     * @param GoogleDriveConnection $connection
     * @return Drive
     * @throws \Exception
     */
    public function getDriveClient(GoogleDriveConnection $connection): Drive
    {
        // Refresh token if expired
        if ($connection->isTokenExpired()) {
            $this->refreshToken($connection);
            $connection->refresh(); // Reload model to get updated token
        }

        $this->client->setAccessToken(['access_token' => $connection->access_token]);
        return new Drive($this->client);
    }

    /**
     * Revoke access token (disconnect)
     *
     * @param GoogleDriveConnection $connection
     * @return bool
     */
    public function revokeToken(GoogleDriveConnection $connection): bool
    {
        try {
            $this->client->revokeToken($connection->access_token);
            return true;
        } catch (\Exception $e) {
            \Log::warning('Failed to revoke Google Drive token', [
                'connection_id' => $connection->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
}
