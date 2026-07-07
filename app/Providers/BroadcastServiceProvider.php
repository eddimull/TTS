<?php

namespace App\Providers;

use Illuminate\Support\Facades\Broadcast;
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
        // One registration for BOTH clients: the `web` group starts the
        // session so browser (Echo) requests can authenticate statefully,
        // while auth:sanctum also accepts the mobile app's Bearer tokens.
        // CSRF is exempted for this route (VerifyCsrfToken::$except) since
        // token clients send none. channels.php must NOT re-register routes.
        Broadcast::routes(['middleware' => ['web', 'auth:sanctum']]);

        require base_path('routes/channels.php');
    }
}
