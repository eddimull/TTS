<?php

namespace App\Http\Controllers;
use Inertia\Inertia;
use Illuminate\Http\Request;
use App\Models\BandEvents;
use App\Models\EventDistanceForMembers;
use App\Models\State;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        // $events = DB::select(DB::raw('SELECT band_events.*,ET.name AS event_type 
        //                         FROM band_events 
        //                         JOIN band_owners BO ON BO.band_id = band_events.band_id 
        //                         JOIN event_types ET ON ET.id = band_events.event_type_id 
        //                         WHERE BO.user_id = ? AND band_events.deleted_at IS NULL'),[Auth::id()]);
        $events = BandEvents::select('band_events.*')
                        ->join('band_members','band_events.band_id','=','band_members.band_id')
                        ->where('event_time','>',date('Y-m-d'))
                        ->where('band_members.user_id',Auth::id())->get();
        $user = Auth::user();
        
        $state = State::where('state_id',$user->StateID)->first();
        // $userGeoCode = \GoogleMaps::load('geocoding')
        //                 ->setParamByKey('address', $user->Address1 . ' ' . $user->City . ' ' . $state->state_name . ' ' . $user->Zip)->get();
        $stats = ['miles'=>0];
        // dd($userGeoCode);
        foreach($events as $event)
        {
            $eventState = State::where('state_id',$event->state_id)->first();
            
            $mileage = EventDistanceForMembers::where('event_id',$event->id)->where('user_id',$user->id)->firstOrCreate();
            // dd($user->Address1 . ' ' . $user->City . ', ' . $state->state_name . ' ' . $user->Zip);
            if(is_null($mileage->miles) || $mileage->created_at < $event->updated_at)
            {
                // dd($event->address_street . ' ' . $event->city . ', ' . $eventState->state_name . ' ' . $event->zip);
                $mileage->event_id = $event->id;
                $mileage->user_id = $user->id;
                $response = \GoogleMaps::load('distancematrix')
                ->setParamByKey('origins', $user->Address1 . ' ' . $user->City . ' ' . $state->state_name . ' ' . $user->Zip)
                ->setParamByKey('destinations', $event->address_street . ' ' . $event->city . ', ' . $eventState->state_name . ' ' . $event->zip)
                ->setParamByKey('units', 'imperial')->get();
                $response = json_decode($response);
                // dd($event->address_street);
                if($response->rows[0]->elements[0]->status == "ZERO_RESULTS")
                {
                    $response = \GoogleMaps::load('distancematrix')
                    ->setParamByKey('origins', $user->Address1 . ' ' . $user->City . ' ' . $state->state_name . ' ' . $user->Zip)
                    ->setParamByKey('destinations', $event->venue_name . ' ' . $event->zip)
                    ->setParamByKey('units', 'imperial')->get();
                    $response = json_decode($response);
                }
                
                $mileage->miles = str_replace(" mi","",$response->rows[0]->elements[0]->distance->text);

                if(str_contains($response->rows[0]->elements[0]->duration->text,'hours'))
                {
                    
                    $mileage->minutes = preg_replace("/[^0-9\.,]/", "", str_replace(" hours",".",$response->rows[0]->elements[0]->duration->text)) * 60;
                }
                if(str_contains($response->rows[0]->elements[0]->duration->text,'hour'))
                {
                    $mileage->minutes = preg_replace("/[^0-9\.,]/", "", str_replace(" hour",".",$response->rows[0]->elements[0]->duration->text)) * 60;
                }
                if(str_contains($response->rows[0]->elements[0]->duration->text,'mins'))
                {
                    $mileage->minutes = preg_replace("/[^0-9\.,]/", "", str_replace(" mins","",$response->rows[0]->elements[0]->duration->text));
                }
                $mileage->save();
            }


            $event->miles = $mileage->miles;

            $stats['miles'] = $stats['miles'] + $event->miles;
            
        }
        return Inertia::render('Dashboard',
        [
            'events'=>$events,
            'stats'=>$stats
        ]);
    }
}
