<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class BroadcastServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        // Do NOT register the /broadcasting/auth route here. routes/channels.php
        // registers it once with the 'auth:sanctum' guard, which authenticates
        // both the web SPA (Sanctum stateful session) and the mobile app (Bearer
        // token). Registering it again here with ['web','auth'] created a DUPLICATE
        // route; with route caching on (prod), the first-registered ['web','auth']
        // match won, rejecting the mobile Bearer token (no web session) — private
        // channel subscriptions silently failed and the client spun forever.
        require base_path('routes/channels.php');
    }
}
