<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;
use App\Models\Bookings;
use App\Models\Conversation;
use App\Models\Events;
use App\Models\Questionnaires;
use App\Policies\BookingsPolicy;
use App\Policies\ConversationPolicy;
use App\Policies\EventsPolicy;
use App\Policies\QuestionnairePolicy;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array
     */
    protected $policies = [
        // 'App\Models\Model' => 'App\Policies\ModelPolicy',
        Bookings::class => BookingsPolicy::class,
        Conversation::class => ConversationPolicy::class,
        Events::class => EventsPolicy::class,
        Questionnaires::class => QuestionnairePolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerPolicies();

        //
    }
}
