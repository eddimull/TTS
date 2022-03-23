<?php 

namespace App\Services;
use App\Models\BandEvents;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class UserEventsService{
    public function getEvents()
    {
        $events = Auth::user()->events
                ->where('event_time','>',Carbon::now());
                // ->where('event_time','<',Carbon::parse('+2 month'));
        return $events;
    }
}