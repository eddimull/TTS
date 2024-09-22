<?php

namespace App\Providers;

use Inertia\Inertia;
use Illuminate\Support\Str;
use App\Models\QuestionnaireComponents;
use Illuminate\Support\ServiceProvider;
use App\Observers\QuestionnaireComponentObserver;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
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

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        QuestionnaireComponents::observe(QuestionnaireComponentObserver::class);
    }
}
