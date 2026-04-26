<?php

namespace App\Providers;

use App\Contracts\StripeClientInterface;
use App\Models\Bands;
use App\Models\QuestionnaireComponents;
use App\Observers\BandObserver;
use App\Observers\QuestionnaireComponentObserver;
use App\Services\GoogleCalendarService;
use App\Services\Stripe\StripeClientWrapper;
use Google\Client;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\ParallelTesting;
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
        ParallelTesting::setUpTestDatabase(function (string $database, int $token) {
            Artisan::call('db:seed');
        });
        
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
