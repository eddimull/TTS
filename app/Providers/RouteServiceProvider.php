<?php

namespace App\Providers;

use Illuminate\Http\Request;
use App\Models\BandCalendars;
use Illuminate\Support\Facades\Route;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;

class RouteServiceProvider extends ServiceProvider
{
    protected $namespace = 'App\Http\Controllers';
    /**
     * The path to the "home" route for your application.
     *
     * This is used by Laravel authentication to redirect users after login.
     *
     * @var string
     */
    public const HOME = '/dashboard';

    /**
     * The controller namespace for the application.
     *
     * When present, controller route declarations will automatically be prefixed with this namespace.
     *
     * @var string|null
     */
    // protected $namespace = 'App\\Http\\Controllers';

    /**
     * Define your route model bindings, pattern filters, etc.
     *
     * @return void
     */
    public function boot()
    {
        $this->configureRateLimiting();

        Route::bind('calendar_id', function ($value) {
            return BandCalendars::where('calendar_id', $value)->firstOrFail();
        });

        $this->routes(function ()
        {
            Route::prefix('api')
                ->middleware('api')
                ->namespace($this->namespace)
                ->group(base_path('routes/api.php'));

            Route::middleware('web')
                ->namespace($this->namespace)
                ->group(function ($router)
                {
                    require base_path('routes/web.php');
                    require base_path('routes/management.php');
                });

            Route::prefix('webhook')
                ->namespace($this->namespace)
                ->group(base_path('routes/webhooks.php'));

            Route::prefix('charts')
                ->middleware('web')
                ->namespace($this->namespace)
                ->group(base_path('routes/charts.php'));

            Route::prefix('portal')
                ->middleware('web')
                ->namespace($this->namespace)
                ->group(base_path('routes/contact.php'));

            // Redirect old /contact/* URLs to new /portal/* URLs
            Route::redirect('/contact/{any}', '/portal/{any}')->where('any', '.*');
        });
    }

    /**
     * Configure the rate limiters for the application.
     *
     * @return void
     */
    protected function configureRateLimiting()
    {
        RateLimiter::for('api', function (Request $request)
        {
            return Limit::perMinute(60)->by(optional($request->user())->id ?: $request->ip());
        });
    }
}
