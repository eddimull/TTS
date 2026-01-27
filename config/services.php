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

    'media' => [
        'upload_notification_delay' => env('MEDIA_UPLOAD_NOTIFICATION_DELAY', 5), // minutes
    ],

];
