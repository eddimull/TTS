<?php

namespace App\Services;

use App\Models\BandEvents;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class UserEventsService
{
    public function getEvents()
    {
        $afterDate = Carbon::now()->subHours(72);
        $events = Auth::user()->getEventsAttribute($afterDate, true); //why isn't the laravel magic happening where I can just specify 'events'
        return $events;
    }
}
