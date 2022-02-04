<?php 

namespace App\Services;
use App\Models\BandEvents;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class UserEventsService{
    public function getEvents()
    {
        $events = DB::select(DB::raw('SELECT band_events.*,ET.name AS event_type 
                                FROM band_events 
                                JOIN band_owners BO ON BO.band_id = band_events.band_id 
                                JOIN event_types ET ON ET.id = band_events.event_type_id 
                                WHERE BO.user_id = ? AND band_events.deleted_at IS NULL 
                                ORDER BY event_time ASC'),[Auth::id()]);

        return $events;
    }
}