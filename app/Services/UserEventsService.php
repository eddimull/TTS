<?php 

namespace App\Services;
use App\Models\BandEvents;
use Illuminate\Support\Facades\Auth;

class UserEventsService{
    public function getEvents()
    {
        $events = BandEvents::select('band_events.*')
                        ->join('band_members','band_events.band_id','=','band_members.band_id')
                        ->where('event_time','>',date('Y-m-d'))
                        ->where('band_members.user_id',Auth::id())->get();

        return $events;
    }
}