<?php

namespace App\Providers;

use App\Contracts\StripeClientInterface;
use App\Services\Stripe\StripeClientWrapper;
use Inertia\Inertia;
use Illuminate\Support\Str;
use App\Models\QuestionnaireComponents;
use App\Models\Bands;
use App\Services\GoogleCalendarService;
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
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
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
