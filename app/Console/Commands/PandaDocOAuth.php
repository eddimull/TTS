<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\File;

class PandaDocOAuth extends Command
{
    protected $signature = 'pandadoc:oauth {--refresh : Refresh existing token}';
    protected $description = 'Authenticate with PandaDoc using OAuth';

    private $clientId;
    private $clientSecret;
    private $tokenUrl;
    private $authUrl;

    public function __construct()
    {
        parent::__construct();
        $this->clientId = config('services.pandadoc.client_id');
        $this->clientSecret = config('services.pandadoc.client_secret');
        $this->tokenUrl = 'https://api.pandadoc.com/oauth2/access_token';
        $this->authUrl = 'https://app.pandadoc.com/oauth2/authorize';
    }

    public function handle()
    {
        if ($this->option('refresh')) {
            return $this->refreshToken();
        }

        $this->info('Starting PandaDoc OAuth process...');
        
        if (!$this->clientId || !$this->clientSecret) {
            $this->error('PandaDoc client ID and secret must be configured in config/services.php');
            return Command::FAILURE;
        }

        // Use localhost redirect URI
        $redirectUri = 'https://local.tts.band:8710/auth/pandadoc/callback';
        $authUrl = $this->generateAuthUrl($redirectUri);
        
        $this->newLine();
        $this->info('🔗 Please visit the following URL to authorize the application:');
        $this->line('');
        $this->line($authUrl);
        $this->line('');
        $this->info('📋 After authorizing:');
        $this->info('   1. You will be redirected to a localhost URL (which may show an error page - that\'s OK!)');
        $this->info('   2. Look at the URL in your browser address bar');
        $this->info('   3. Copy the "code" parameter from the URL');
        $this->info('   4. Example: https://local.tts.band:8710/auth/pandadoc/callback?code=ABC123&state=xyz');
        $this->info('   5. Copy just the ABC123 part (everything after code= and before &)');
        $this->newLine();
        
        // Prompt for the authorization code
        $authCode = $this->ask('Enter the authorization code from the URL:');

        if (empty($authCode)) {
            $this->error('Authorization code is required');
            return Command::FAILURE;
        }

        // Exchange the authorization code for an access token
        try {
            $response = Http::asForm()->post($this->tokenUrl, [
                'grant_type' => 'authorization_code',
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'code' => trim($authCode),
                'redirect_uri' => $redirectUri,
            ]);

            if ($response->successful()) {
                $tokenData = $response->json();
                $this->storeTokens($tokenData);
                $this->info('✅ Authentication successful! Tokens have been stored.');
                return Command::SUCCESS;
            } else {
                $this->error('❌ Authentication failed: ' . $response->body());
                $this->info('💡 Make sure the authorization code is correct and hasn\'t expired');
                return Command::FAILURE;
            }
        } catch (\Exception $e) {
            $this->error('❌ Error during token exchange: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function generateAuthUrl($redirectUri)
    {
        $params = [
            'client_id' => $this->clientId,
            'redirect_uri' => $redirectUri,
            'scope' => 'read+write',
            'response_type' => 'code',
            'state' => bin2hex(random_bytes(16)),
        ];

        return $this->authUrl . '?' . http_build_query($params);
    }

    private function storeTokens($tokenData, $isRefresh = false)
    {
        $accessToken = $tokenData['access_token'];
        $refreshToken = $tokenData['refresh_token'] ?? config('services.pandadoc.refresh_token');
        $expiresIn = $tokenData['expires_in'] ?? 3600;
        $expiresAt = time() + $expiresIn;

        // Update .env file
        $this->updateEnvFile([
            'PANDADOC_ACCESS_TOKEN' => $accessToken,
            'PANDADOC_REFRESH_TOKEN' => $refreshToken,
            'PANDADOC_TOKEN_EXPIRES_AT' => $expiresAt,
        ]);

        $action = $isRefresh ? 'refreshed' : 'stored';
        $this->info("🔐 Tokens {$action} successfully");
        
        $this->table(['Token Type', 'Value (truncated)', 'Expires'], [
            ['Access Token', substr($accessToken, 0, 20) . '...', date('Y-m-d H:i:s', $expiresAt)],
            ['Refresh Token', substr($refreshToken, 0, 20) . '...', 'Never (until revoked)'],
        ]);
    }

    private function updateEnvFile($data)
    {
        $envPath = base_path('.env');
        
        if (!File::exists($envPath)) {
            $this->error('.env file not found');
            return;
        }

        $envContent = File::get($envPath);

        foreach ($data as $key => $value) {
            $pattern = "/^{$key}=.*/m";
            $replacement = "{$key}={$value}";
            
            if (preg_match($pattern, $envContent)) {
                $envContent = preg_replace($pattern, $replacement, $envContent);
            } else {
                $envContent .= "\n{$replacement}";
            }
        }

        File::put($envPath, $envContent);
        
        // Clear config cache to pick up new values
        if (app()->environment('production')) {
            $this->call('config:cache');
        }
    }
}