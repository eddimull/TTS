<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="94R9Fxp061AJERSGHgf39YHORsa2GaDoomPXsOLM">

        <title>{{$event['event_name']}} Advance</title>
        <!-- tailwind -->
        <link href="https://unpkg.com/tailwindcss@^2/dist/tailwind.min.css" rel="stylesheet">
        <!-- Fonts -->
        <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700&display=swap">
    </head>
    <body class="font-sans antialiased">
    <div class="min-w-full max-w-7xl mx-auto sm:px-6 lg:px-8 background-white-400">
                <!-- {{ $event }}  -->
            <div id="advance">
                <div class="text-center italicized mb-4">
                               
                {{ $event['band']['name'] }} Advance
                </div>
                <table class="min-w-full bg-white m-5 rounded border border-2" >
                <tr class="min-w-full text-center border border-2 px-4 py-2 text-black-600 font-large"><td class="border border-black border-2 px-4" colspan="3"><div class="text-center">{{ $event['event_type_name']['name'] }}</div></td></tr>
                    <tr class="min-w-full text-center border border-2 px-4 py-2 text-black-600 font-large"><td class="border border-black border-2 px-4" colspan="3"><div class="text-center">{{ $event['event_name'] }}</div></td></tr>
                    <tr>
                        <td class="border border-black border-2 px-4 py-2 w-1/6 bg-green-100">Date: <br/>{{ date('m/d/Y',strtotime($event['event_time'])) }}</td>
                        <td class="border border-black border-2 px-4 py-2 w-1/6 bg-gray-200">Location:</td>
                        <td class="border border-black border-2 px-4 py-2">
                            {{$event['venue_name']}}<br/>
                            {{$event['address_street']}}<br/>
                            {{$event['city']}}, {{$event['state']['state_name']}}<br/>
                            {{$event['zip']}}<br/>
                            
                        </td>
                    </tr>
                    <tr>
                        <td class="w-1/6">&nbsp;</td>
                        <td class="border border-black border-2 px-4 py-2 w-1/6 bg-gray-200">Google Maps:</td>
                        <td class="border border-black border-2 px-4 py-2">
                            <a href="https://www.google.com/maps/search/?api=1&query={{urlencode($event['venue_name'] . ' ' . $event['address_street'] . ' ' . $event['city'] . ', ' . $event['state']['state_name'] . ' ' . $event['zip'])}}">
                                <img src="/events/{{$event['event_key']}}/locationImage"/>
                            </a>
                        </td>
                    </tr>
                    <tr>
                        <td class="w-1/6">&nbsp;</td>
                        <td class="border border-black border-2 px-4 py-2 w-1/6 bg-gray-200">Apple Maps:</td>
                        <td class="border border-black border-2 px-4 py-2">
                           <a href="https://maps.apple.com/?q={{urlencode($event['venue_name'] . ' ' . $event['address_street'] . ' ' . $event['city'] . ', ' . $event['state']['state_name'] . ' ' . $event['zip'])}}">https://maps.apple.com/?q={{urlencode($event['venue_name'] . ' ' . $event['address_street'] . ' ' . $event['city'] . ', ' . $event['state']['state_name'] . ' ' . $event['zip'])}}</a>
                        </td>
                    </tr>
                    <tr>
                        <td class="w-1/6">&nbsp;</td>
                        <td class="border border-black border-2 px-4 py-2 w-1/6 bg-gray-200">Contact:</td>
                        <td class="border border-black border-2 px-4 py-2">
                            
                        </td>
                    </tr>
                    <tr>
                        <td class="w-1/6">&nbsp;</td>
                        <td class="border border-black border-2 px-4 py-2 w-1/6 bg-gray-200">Production:</td>
                        <td class="border border-black border-2 px-4 py-2">
                            @if($event['production_needed'] == 1)
                                Production Needed
                            @else
                                Production Provided
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td class="w-1/6">&nbsp;</td>
                        <td class="border border-black border-2 px-4 py-2 w-1/6 bg-gray-200">Backline:</td>
                        <td class="border border-black border-2 px-4 py-2">
                            @if($event['backline_provided'] == 1)
                                Backline is provided
                            @else
                                Backline is not provided
                            @endif
                        </td>
                    </tr>                    
                    <tr>
                        <td class="w-1/6">&nbsp;</td>
                        <td class="border border-black border-2 px-4 py-2 w-1/6 bg-gray-200">Load In:</td>
                        <td class="border border-black border-2 px-4 py-2">
                            Prod: {{ date('g:i A', strtotime($event['production_loadin_time'])) }}<br/>
                            Drums: {{  date('g:i A', strtotime($event['rhythm_loadin_time'])) }}<br/>
                            Band: {{  date('g:i A', strtotime($event['band_loadin_time'])) }}<br/>
                        </td>                            
                    </tr>
                    @if($event['event_type']['name'] == 'Wedding')     
                    <tr>
                        <td class="w-1/6">&nbsp;</td>
                        <td class="border border-black border-2 px-4 py-2 w-1/6 bg-gray-200">Ceremony Time:</td>
                        <td class="border border-black border-2 px-4 py-2">
                            
                            {{ date('g:i A', strtotime($event['ceremony_time'])) }} {{ $event['onsite'] ? 'Onsite ceremony' : 'Ceremony Offsite'}}
                        </td>                                                    
                    </tr>  
                    @endif
                    <tr>
                        <td class="w-1/6">&nbsp;</td>
                        <td class="border border-black border-2 px-4 py-2 w-1/6 bg-gray-200">Quiet Time:</td>
                        <td class="border border-black border-2 px-4 py-2">
                            {{  date('g:i A', strtotime($event['quiet_time'])) }}
                        </td>                            
                    </tr>       
                    <tr>
                        <td class="w-1/6">&nbsp;</td>
                        <td class="border border-black border-2 px-4 py-2 w-1/6 bg-gray-200">Show Time:</td>
                        <td class="border border-black border-2 px-4 py-2">
                            {{ date('g:i A', strtotime($event['event_time'])) }}
                        </td>                            
                    </tr>       
                    <tr>
                        <td class="w-1/6">&nbsp;</td>
                        <td class="border border-black border-2 px-4 py-2 w-1/6 bg-gray-200">End Time:</td>
                        <td class="border border-black border-2 px-4 py-2">
                            {{ date('g:i A', strtotime($event['end_time'])) }}
                        </td>                            
                    </tr>              
                    @if($event['event_type']['name'] == 'Wedding')                           
                    <tr>
                        <td class="w-1/6">&nbsp;</td>
                        <td class="border border-black border-2 px-4 py-2 w-1/6 bg-gray-200">Ceremony Music:</td>
                        <td class="border border-black border-2 px-4 py-2">
                            DJ
                        </td>                                                    
                    </tr>
                    @endif
                    <tr>
                        <td class="w-1/6">&nbsp;</td>
                        <td class="border border-black border-2 px-4 py-2 w-1/6 bg-gray-200">Attire:</td>
                        <td class="border border-black border-2 px-4 py-2">
                            {{$event['colorway'] ? $event['colorway']['color_title'] : 'TBD'}}
                        </td>                                                    
                    </tr>       
                    @if($event['event_type']['name'] == 'Wedding')             
                        <tr>
                            <td class="w-1/6">&nbsp;</td>
                            <td class="border border-black border-2 px-4 py-2 w-1/6 bg-gray-200">First Dance:</td>
                            <td class="border border-black border-2 px-4 py-2">
                                {{$event['first_dance']}}
                            </td>                                                    
                        </tr>                      
                        <tr>
                            <td class="w-1/6">&nbsp;</td>
                            <td class="border border-black border-2 px-4 py-2 w-1/6 bg-gray-200">Father / Daughter:</td>
                            <td class="border border-black border-2 px-4 py-2">
                                {{$event['father_daughter']}}
                            </td>                                                    
                        </tr>                                             
                        <tr>
                            <td class="w-1/6">&nbsp;</td>
                            <td class="border border-black border-2 px-4 py-2 w-1/6 bg-gray-200">Mother / Groom:</td>
                            <td class="border border-black border-2 px-4 py-2">
                                {{$event['mother_groom']}}
                            </td>                                                    
                        </tr>                                                                 
                        <tr>
                            <td class="w-1/6">&nbsp;</td>
                            <td class="border border-black border-2 px-4 py-2 w-1/6 bg-gray-200">Bouquet/Garter:</td>
                            <td class="border border-black border-2 px-4 py-2">
                                {{$event['bouquet_garter']}}
                            </td>                                                    
                        </tr>    
                        <tr>
                            <td class="w-1/6">&nbsp;</td>
                            <td class="border border-black border-2 px-4 py-2 w-1/6 bg-gray-200">Money Dance:</td>
                            <td class="border border-black border-2 px-4 py-2">
                                {{$event['money_dance']}}
                            </td>                                                    
                        </tr>                        
                        <tr>
                            <td class="w-1/6">&nbsp;</td>
                            <td class="border border-black border-2 px-4 py-2 w-1/6 bg-gray-200">Second Line:</td>
                            <td class="border border-black border-2 px-4 py-2">
                                {{$event['second_line'] ? 'Yes':'No'}}
                            </td>                                                    
                        </tr>     
                    @endif 
                    <tr>
                        <td class="w-1/6">&nbsp;</td>
                        <td class="border border-black border-2 px-4 py-2 w-1/6 bg-gray-200">Set Times:</td>
                        <td class="border border-black border-2 px-4 py-2">
                            TBD
                        </td>                                                    
                    </tr>    
                    <tr>
                        <td class="w-1/6">&nbsp;</td>
                        <td class="border border-black border-2 px-4 py-2 w-1/6 bg-gray-200">Notes:</td>
                        <td class="border border-black border-2 px-4 py-2">
                            {{$event['notes']}}
                        </td>                                                    
                    </tr>       
                    <tr v-if="($event.lodging)">
                        <td class="w-1/6">&nbsp;</td>
                        <td class="border border-black border-2 px-4 py-2 w-1/6 bg-gray-200">Lodging:</td>
                        <td class="border border-black border-2 px-4 py-2">
                            There will be lodging.
                        </td>                                                    
                    </tr>    
                    <tr>
                        <td class="w-1/6">&nbsp;</td>
                        <td class="border border-black border-2 px-4 py-2 w-1/6 bg-gray-200">Outside:</td>
                        <td class="border border-black border-2 px-4 py-2">
                            {{$event['outside'] ? 'Gig is outside' : 'Gig is inside'}}
                        </td>                                                    
                    </tr>                                                                 
                </table>
            </div>
        </div>
    </body>
</html>