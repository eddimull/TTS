<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Broadcaster
    |--------------------------------------------------------------------------
    |
    | This option controls the default broadcaster that will be used by the
    | framework when an event needs to be broadcast. You may set this to
    | any of the connections defined in the "connections" array below.
    |
    | Supported: "pusher", "ably", "redis", "log", "null"
    |
    */

    'default' => env('BROADCAST_DRIVER', 'null'),

    /*
    |--------------------------------------------------------------------------
    | Broadcast Connections
    |--------------------------------------------------------------------------
    |
    | Here you may define all of the broadcast connections that will be used
    | to broadcast events to other systems or over websockets. Samples of
    | each available type of connection are provided inside this array.
    |
    */

    'connections' => [

        'pusher' => [
            'driver' => 'pusher',
            'key' => env('PUSHER_APP_KEY'),
            'secret' => env('PUSHER_APP_SECRET'),
            'app_id' => env('PUSHER_APP_ID'),
            'options' => [
                'cluster' => env('PUSHER_APP_CLUSTER'),
                // When PUSHER_HOST is unset, fall back to Pusher Cloud's host for
                // the configured cluster (api-<cluster>.pusher.com) rather than a
                // self-hosted default. A blank/absent PUSHER_HOST therefore means
                // "use real Pusher", while a self-hosted soketi can still override
                // host/port/scheme via env.
                //
                // The mobile client (pusher_channels_flutter) connects to
                // ws-<cluster>.pusher.com and cannot target a custom host, so the
                // server MUST broadcast to the same Pusher Cloud endpoint or events
                // never reach the device (silent: no error, message marked complete,
                // client spins forever).
                // Use ?: (not env()'s default arg) so a set-but-empty env var
                // (PUSHER_HOST= / PUSHER_PORT=) still falls through to the Pusher
                // Cloud default — env('KEY', $default) only applies $default when
                // the key is absent, not when it's present-but-blank.
                'host' => env('PUSHER_HOST') ?: 'api-' . env('PUSHER_APP_CLUSTER') . '.pusher.com',
                'port' => env('PUSHER_PORT') ?: 443,
                'scheme' => env('PUSHER_SCHEME') ?: 'https',
                'useTLS' => (env('PUSHER_SCHEME') ?: 'https') === 'https',
            ],
        ],

        'ably' => [
            'driver' => 'ably',
            'key' => env('ABLY_KEY'),
        ],

        'redis' => [
            'driver' => 'redis',
            'connection' => 'default',
        ],

        'log' => [
            'driver' => 'log',
        ],

        'null' => [
            'driver' => 'null',
        ],

    ],

];
