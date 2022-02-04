<?php 

namespace App\Services;
use App\Models\BandEvents;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class UserEventsService{
    public function getEvents()
    {
        $bands = [0];
        $bandOwner = Auth::user()->bandOwner;
        $bandMember = Auth::user()->bandMember;
        foreach($bandOwner as $band)
        {
            array_push($bands,$band->id);
        }

        foreach($bandMember as $band)
        {
            array_push($bands,$band->id);
        }
        // dd(implode(',',$bands));
        $list = implode(',',$bands);
        $events = DB::select(DB::raw('SELECT band_events.*,ET.name AS event_type 
        FROM band_events 
        JOIN event_types ET ON ET.id = band_events.event_type_id 
        WHERE band_id IN (' . $list . ') AND DATE(event_time) < DATE(DATE_ADD(NOW(),INTERVAL 45 DAY)) AND band_events.deleted_at IS NULL 
        ORDER BY event_time DESC'));

        return $events;
    }
}