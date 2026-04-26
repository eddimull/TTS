<?php

namespace App\Providers;

use App\Contracts\StripeClientInterface;
use App\Services\Stripe\StripeClientWrapper;
use Inertia\Inertia;
use Illuminate\Support\Str;
use App\Models\QuestionnaireComponents;
use App\Models\Bands;
use App\Services\GoogleCalendarService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\ParallelTesting;
use Illuminate\Support\ServiceProvider;
use App\Observers\QuestionnaireComponentObserver;
use App\Observers\BandObserver;
use Google\Client;

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
        // Laravel's parallel testing only rewrites the connection's database for
        // tests that use a database trait. Tests without one still hit the DB via
        // shared middleware (e.g. HandleInertiaRequests::share -> EventTypes::all),
        // so apply the per-token suffix to the default connection for every test.
        // No-op in sequential mode — these callbacks only fire under --parallel.
        if ($this->app->environment('testing')) {
            $rewriteDatabase = function ($token) {
                $default = config('database.default');
                $original = config("database.connections.{$default}.database");
                $suffix = "_test_{$token}";
                if (! str_ends_with($original, $suffix)) {
                    config(["database.connections.{$default}.database" => $original . $suffix]);
                    DB::purge($default);
                }
            };
            ParallelTesting::setUpProcess($rewriteDatabase);
            ParallelTesting::setUpTestCase(fn ($token) => $rewriteDatabase($token));
        }

        QuestionnaireComponents::observe(QuestionnaireComponentObserver::class);
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
