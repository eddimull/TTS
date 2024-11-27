<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class PandaDocOAuth extends Command
{
    protected $signature = 'pandadoc:oauth';
    protected $description = 'Authenticate with PandaDoc using OAuth';

    private $clientId;
    private $clientSecret;
    private $tokenUrl;

    public function __construct()
    {
        parent::__construct();
        $this->clientId = config('services.pandadoc.client_id');
        $this->clientSecret = config('services.pandadoc.client_secret');
        $this->tokenUrl = 'https://api.pandadoc.com/oauth2/access_token';
    }

    public function handle()
    {
        $this->info('Starting PandaDoc OAuth process...');

        // Step 1: Prompt for the authorization code
        $authCode = $this->ask('Please visit the PandaDoc authorization URL and enter the authorization code:');

        // Step 2: Exchange the authorization code for an access token
        $response = Http::asForm()->post($this->tokenUrl, [
            'grant_type' => 'authorization_code',
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'code' => $authCode,
        ]);

        if ($response->successful())
        {
            $accessToken = $response->json()['access_token'];
            $refreshToken = $response->json()['refresh_token'];

            // Store these tokens securely (e.g., in a database or .env file)
            $this->storeTokens($accessToken, $refreshToken);

            $this->info('Authentication successful! Access token and refresh token have been stored.');
        }
        else
        {
            $this->error('Authentication failed: ' . $response->body());
        }
    }

    private function storeTokens($accessToken, $refreshToken)
    {
        // Implement secure storage of tokens here
        // For example, you could store them in the database or update your .env file
        // This is a placeholder implementation
        $this->info('Access Token: ' . $accessToken);
        $this->info('Refresh Token: ' . $refreshToken);
        $this->warn('Remember to store these tokens securely in your application!');
    }
}
