@extends('blank')

@section('content')
    <div class="max-w-lg mx-auto drop-shadow-md rounded-lg lg:px-8">
                <div id="advanceContainer" class="rounded-lg bg-white pb-4 mt-3">
                    <div class="bg-blue-500 rounded-t-lg  text-white font-bold mb-4 py-2 sticky top-0">
                        <div class=" text-center italicized mb-4">
                                    
                        {{ $event['band']['name'] }}
                        </div> 
                        <div class="flex flex-row justify-around">
                            <div>{{ $event['event_name'] }}</div>    
                            <div>{{ date('m/d/Y',strtotime($event['event_time'])) }}</div>
                        </div>
                    </div>
                    <div class="px-6 mb-2">
                        <div class="-ml-2 font-bold">Venue:</div>
                        <div>                            {{$event['venue_name']}}<br/>
                            {{$event['address_street']}}<br/>
                            {{$event['city']}}, {{$event['state']['state_name']}}<br/>
                            {{$event['zip']}}<br/></div>
                    </div>
                    <div id="maps" class="flex sm:flex-row flex-col justify-center sm:justify-around px-6 mb-4">
                        <div class="sm:max-w-xs p-4 flex flex-row sm:flex-col justify-around content-center items-center">
                            <img src="/events/{{$event['event_key']}}/locationImage" alt="googleMap"/>
                            
                        </div>
                        <div class="flex flex-row sm:flex-col justify-around content-center">
                            <div style="max-width:60px">
                                <a href="https://www.google.com/maps/search/?api=1&query={{urlencode($event['venue_name'] . ' ' . $event['address_street'] . ' ' . $event['city'] . ', ' . $event['state']['state_name'] . ' ' . $event['zip'])}}">
                                    <img src="/images/googlemaps.png" alt="googleMapsLink"/>
                                    <div class="text-xs whitespace-nowrap">Google Maps</div>
                                </a>
                            </div>
                            <div style="max-width:60px">
                                <a href="https://maps.apple.com/?q={{urlencode($event['venue_name'] . ' ' . $event['address_street'] . ' ' . $event['city'] . ', ' . $event['state']['state_name'] . ' ' . $event['zip'])}}">
                                    <img src="/images/applemaps.png" alt="appleMapsLink"/>
                                    <div class="text-xs whitespace-nowrap">Apple Maps</div>
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="px-6 mb-4">
                        <div class="-ml-2 font-bold">Schedule:</div>
                        <div class="grid grid-cols-2 border">                            
                            <div class="border text-center">{{ date('g:i A', strtotime($event['production_loadin_time'])) }}</div><div class="border px-2">Production Load In</div>
                            <div class="border text-center">{{  date('g:i A', strtotime($event['rhythm_loadin_time'])) }}</div><div class="border px-2">Rhythm Load In</div>
                            <div class="border text-center">{{  date('g:i A', strtotime($event['band_loadin_time'])) }}</div><div class="border px-2">Band Load In</div>
                            @if($event['event_type']['name'] == 'Wedding') 
                            <div class="border text-center">{{ date('g:i A', strtotime($event['ceremony_time'])) }}</div><div class="border px-2">{{ $event['onsite'] ? 'Onsite ceremony' : 'Ceremony Offsite'}}</div>
                            @endif
                            <div class="border text-center">{{  date('g:i A', strtotime($event['quiet_time'])) }}</div><div class="border px-2 uppercase">Quiet</div>
                            <div class="border text-center">{{ date('g:i A', strtotime($event['event_time'])) }}</div><div class="border px-2 uppercase">show time</div>
                            <div class="border text-center">{{ date('g:i A', strtotime($event['end_time'])) }}</div><div class="border px-2 uppercase">end</div>
                        </div>
                    </div>
                    <div class="px-6 mb-4">
                        <div class="-ml-2 font-bold">Attire:</div>
                        <div>{!!$event['colorway_text'] ? $event['colorway_text'] : 'TBD'!!}</div>
                    </div>
                    <div class="px-6 mb-4">
                        <div class="-ml-2 font-bold">Details:</div>
                        <div class="grid grid-cols-2 border">
                            <div class="border text-center">Production:</div>
                            <div class="border px-2"> 
                                @if($event['production_needed'] == 1)
                                    Production Needed
                                @else
                                    Production Provided
                                @endif
                            </div>
                            <div class="border text-center">Backline:</div>
                            <div class="border px-2"> 
                                @if($event['backline_provided'] == 1)
                                    Backline is provided
                                @else
                                    Backline is not provided
                                @endif
                            </div>
                            <div class="border text-center">Lodging:</div>
                            <div class="border px-2"> 
                                @if($event['lodging'])
                                    🏨
                                @else
                                    👎
                                @endif
                            </div>                            
                        </div>
                    </div>
                    <div class="px-6">
                        <div class="-ml-2 font-bold">Notes:</div>
                        <div class="bg-gray-100 p-4">{!!$event['colorway_text'] ? $event['colorway_text'] : 'TBD'!!}</div>
                    </div>
                </div>
        </div>
@stop