<?php

namespace App\Providers;

use App\Contracts\StripeClientInterface;
use App\Models\Bands;
use App\Observers\BandObserver;
use App\Services\GoogleCalendarService;
use App\Services\Stripe\StripeClientWrapper;
use Google\Client;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\ParallelTesting;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Inertia\Inertia;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->bind(GoogleCalendarService::class, function ($app) {
            return new GoogleCalendarService(new Client());
        });

        $this->app->bind(StripeClientInterface::class, function ($app) {
            return new StripeClientWrapper();
        });

        // Register Telescope only in local/development environments
        if ($this->app->environment('local', 'development') && class_exists(\Laravel\Telescope\TelescopeServiceProvider::class)) {
            $this->app->register(\App\Providers\TelescopeServiceProvider::class);
        }
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        // Force Vite to resolve via the build manifest (not the dev server)
        // when running Dusk tests — even if a contributor has `npm run dev`
        // running, the selenium container can't reach 127.0.0.1:5173.
        if (env('VITE_FORCE_BUILD')) {
            Vite::useHotFile(storage_path('framework/vite.dusk.never-exists'));
        }

        ParallelTesting::setUpProcess(function() {
            Artisan::call('migrate:fresh', ['--seed' => true]);
        });
        Bands::observe(BandObserver::class);
        Inertia::share([
            'config' => [
                'StripeInvoiceURL' => config('services.stripe.invoice_url'),
            ],
        ]);
        Inertia::share('app.name', config('app.name'));
        Inertia::share('errors', function ()
        {
            return session()->get('errors') ? session()->get('errors')->getBag('default') . getMessages() : (object)[];
        });
        Inertia::share('successMessage', function ()
        {
            return session()->get('successMessage') ? session()->get('successMessage') : null;
        });
        Inertia::share('warningMessage', function ()
        {
            return session()->get('warningMessage') ? session()->get('warningMessage') : null;
        });
    }
}
