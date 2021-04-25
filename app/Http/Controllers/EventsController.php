<?php

namespace App\Http\Controllers;

use App\Models\EventTypes;
use App\Models\State;
use App\Models\Bands;
use App\Models\BandEvents;
use Doctrine\DBAL\Events;
use Illuminate\Support\Facades\DB;
use Faker\Provider\Uuid;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class EventsController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $events = DB::select(DB::raw('SELECT band_events.*,ET.name AS event_type 
                                FROM band_events 
                                JOIN band_owners BO ON BO.band_id = band_events.band_id 
                                JOIN event_types ET ON ET.id = band_events.event_type_id 
                                WHERE BO.user_id = ?'),[Auth::id()]);

                                
        return Inertia::render('Events/Index',[
            'events'=>$events
        ]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $eventTypes = EventTypes::orderBy('name')->get();
        $states = State::where('country_id',231)->get();
        $bands = Bands::select('bands.*')->join('band_owners','bands.id','=','band_owners.band_id')->where('user_id',Auth::id())->get();
        return Inertia::render('Events/Create',[
            'eventTypes' => $eventTypes,
            'states'=>$states,
            'bands'=>$bands
        ]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
        $request->validate([
            'event_name'=>'required'
        ]);
        // dd($request);
        $strtotime = strtotime($request->event_time);
        $formattedTime = date('Y-m-d',$strtotime);
        BandEvents::create([
            'band_id' => $request->band_id,
            'event_name' => $request->event_name,
            'venue_name' => $request->venue_name,
            'first_dance' => $request->first_dance,
            'second_dance' => $request->second_dance,
            'money_dance' => $request->money_dance,
            'bouquet_dance' => $request->bouquet_dance,
            'address_street' => $request->address_street,
            'zip' => $request->zip,
            'notes' => $request->notes,
            'event_time' => $formattedTime,
            'band_loadin_time' => $formattedTime,
            'finish_time' => $formattedTime,
            'rhythm_loadin_time' => $formattedTime,
            'production_loadin_time' => $formattedTime,
            'pay' => $request->pay,
            'depositReceived' => $request->depositReceived,
            'event_key' => $request->event_key,
            'created_at' => $request->created_at,
            'updated_at' => $request->updated_at,
            'public' => $request->public,
            'event_type_id' => $request->event_type_id,
            'lodging' => $request->lodging,
            'state_id' => $request->state_id,
            'event_key'=>Str::uuid()
        ]);

        return redirect()->route('events')->with('successMessage','Event was successfully added');
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($key)
    {
        $eventTypes = EventTypes::orderBy('name')->get();
        $event = BandEvents::where('event_key',$key)->first();
        $states = State::where('country_id',231)->get();
        $bands = Bands::select('bands.*')->join('band_owners','bands.id','=','band_owners.band_id')->where('user_id',Auth::id())->get();
        
        return Inertia::render('Events/Edit',[
            'event'=>$event,
            'eventTypes' => $eventTypes,
            'states'=>$states,
            'bands'=>$bands
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'event_name'=>'required'
        ]);
        // dd($request);
        $strtotime = strtotime($request->event_time);
        $formattedTime = date('Y-m-d',$strtotime);
        BandEvents::create([
            'band_id' => $request->band_id,
            'event_name' => $request->event_name,
            'venue_name' => $request->venue_name,
            'first_dance' => $request->first_dance,
            'second_dance' => $request->second_dance,
            'money_dance' => $request->money_dance,
            'bouquet_dance' => $request->bouquet_dance,
            'address_street' => $request->address_street,
            'zip' => $request->zip,
            'notes' => $request->notes,
            'event_time' => $formattedTime,
            'band_loadin_time' => $formattedTime,
            'finish_time' => $formattedTime,
            'rhythm_loadin_time' => $formattedTime,
            'production_loadin_time' => $formattedTime,
            'pay' => $request->pay,
            'depositReceived' => $request->depositReceived,
            'event_key' => $request->event_key,
            'created_at' => $request->created_at,
            'updated_at' => $request->updated_at,
            'public' => $request->public,
            'event_type_id' => $request->event_type_id,
            'lodging' => $request->lodging,
            'state_id' => $request->state_id,
            'event_key'=>Str::uuid()
        ]);

        return redirect()->route('events')->with('successMessage',$request->event_name . ' was successfully updated');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($key)
    {
        //
        dd('destroy event');
    }
}
