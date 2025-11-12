<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return string|null
     */
    public function version(Request $request)
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function share(Request $request)
    {
        // Check if user is authenticated via contact guard
        $contactUser = $request->user('contact');

        if ($contactUser) {
            // Contact portal user - minimal auth data, no navigation
            return array_merge(parent::share($request), [
                'auth' => [
                    'user' => [
                        'id' => $contactUser->id,
                        'name' => $contactUser->name,
                        'email' => $contactUser->email,
                        'type' => 'contact',
                    ],
                ],
                'flash' => [
                    'status' => fn () => $request->session()->get('status'),
                ],
            ]);
        }

        // Regular user authentication (use 'web' guard explicitly)
        $webUser = $request->user('web');

        return array_merge(parent::share($request), [
            'auth' => [
                'user' => $webUser ? [
                    'id' => $webUser->id,
                    'name' => $webUser->name,
                    'email' => $webUser->email,
                    'navigation' => $webUser->getNav(),
                    'notifications' => $webUser->notifications,
                ] : null,
            ],
            'flash' => [
                'status' => fn () => $request->session()->get('status'),
            ],
            'eventTypes' => Cache::remember('event_types', 3600, function () {
                return \App\Models\EventTypes::all();
            }),
        ]);
    }
}
