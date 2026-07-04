<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'anthropic' => [
        'key' => env('ANTHROPIC_API_KEY'),
    ],

    'gemini' => [
        'key' => env('GEMINI_API_KEY'),
    ],

    'pandadoc' => [
        'api_key' => env('PANDADOC_KEY'),
        'client_id' => env('PANDADOC_CLIENT_ID'),
        'client_secret' => env('PANDADOC_CLIENT_SECRET'),
        'access_token' => env('PANDADOC_ACCESS_TOKEN'),
        'refresh_token' => env('PANDADOC_REFRESH_TOKEN'),
        'token_expires_at' => env('PANDADOC_TOKEN_EXPIRES_AT'),
    ],

    'stripe' => [
        'key' => env('STRIPE_KEY'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        'invoice_url' => env('STRIPE_INVOICE_URL'),
    ],

    'google_drive' => [
        'client_id' => env('GOOGLE_DRIVE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_DRIVE_CLIENT_SECRET'),
        'redirect_uri' => env('APP_URL') . '/media/drive/callback',
    ],

    'getsongbpm' => [
        'key' => env('GETSONGBPM_API_KEY'),
    ],

    'media' => [
        'upload_notification_delay' => env('MEDIA_UPLOAD_NOTIFICATION_DELAY', 5), // minutes
    ],

    // Store listings for the Bandmate mobile app; the invite landing page
    // hides each button until its URL is configured.
    'mobile_app' => [
        'app_store_url' => env('APP_STORE_URL'),
        'play_store_url' => env('PLAY_STORE_URL'),
    ],

    'google' => [
        'client_id'     => env('GOOGLE_SIGNIN_CLIENT_ID'),
        'client_secret' => env('GOOGLE_SIGNIN_CLIENT_SECRET'),
        'redirect'      => env('APP_URL') . '/auth/google/callback',
        // id_token `aud` whitelist: web client id + Android + iOS client ids, comma-separated.
        'allowed_client_ids' => array_filter(explode(',', env('GOOGLE_SIGNIN_ALLOWED_CLIENT_IDS', ''))),
    ],

    'facebook' => [
        'client_id'     => env('FACEBOOK_CLIENT_ID'),
        'client_secret' => env('FACEBOOK_CLIENT_SECRET'),
        'redirect'      => env('APP_URL') . '/auth/facebook/callback',
    ],

    'apple' => [
        'client_id'     => env('APPLE_SERVICES_CLIENT_ID'),   // Services ID (web flow)
        'client_secret' => env('APPLE_CLIENT_SECRET'),         // pre-generated JWT, expires ≤6 months
        // When these three are set, the client_secret is auto-minted at runtime
        // from the .p8 key (see AppleClientSecretGenerator), superseding APPLE_CLIENT_SECRET.
        'private_key'   => env('APPLE_PRIVATE_KEY_BASE64'),    // base64 of the .p8 file contents
        'key_id'        => env('APPLE_KEY_ID'),
        'team_id'       => env('APPLE_TEAM_ID'),
        'redirect'      => env('APP_URL') . '/auth/apple/callback',
        // id_token `aud` whitelist: iOS bundle id + Services ID, comma-separated.
        'allowed_client_ids' => array_filter(explode(',', env('APPLE_SIGNIN_ALLOWED_CLIENT_IDS', ''))),
    ],

];
