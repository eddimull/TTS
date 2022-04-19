<?php

namespace App\Services;
use App\Models\State;
use Illuminate\Support\Facades\Auth;
use App\Models\EventDistanceForMembers;

class MileageService{
    public function handle($events)
    {
        $user = Auth::user();
        $stats = ['miles'=>0];
        if($user->Address1 !== null && $user->City !== null && $user->StateID !== null && $user->Zip !== null )
        {
            $state = State::where('state_id',$user->StateID)->first();
            // $userGeoCode = \GoogleMaps::load('geocoding')
            //                 ->setParamByKey('address', $user->Address1 . ' ' . $user->City . ' ' . $state->state_name . ' ' . $user->Zip)->get();

            foreach($events as $event)
            {
                $eventState = State::where('state_id',$event->state_id)->first();
                
                $mileage = EventDistanceForMembers::where('event_id',$event->id)->where('user_id',$user->id)->firstOrCreate();
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
                    
                    $mileage->miles = preg_replace('/\D/', '', $response->rows[0]->elements[0]->distance->text);
                    
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
        }

        return $stats;
    }
}