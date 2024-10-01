@extends('blank')

@section('content')

@php
$address = $event->eventable->venue_address;

// Extract zip code
preg_match('/\b\d{5}\b/', $address, $zip_matches);
$zip = $zip_matches[0] ?? '';

// Remove zip code from address
$address = preg_replace('/\b\d{5}\b/', '', $address);

// Extract state (assuming it's always two capital letters before the zip)
preg_match('/\b[A-Z]{2}\b/', $address, $state_matches);
$state = $state_matches[0] ?? '';

// Remove state from address
$address = preg_replace('/\b[A-Z]{2}\b/', '', $address);

// Split the remaining address into parts
$parts = array_map('trim', explode(',', $address));

// The last part should be the city
$city = array_pop($parts);

// The rest is the street address
$street = implode(', ', $parts);
@endphp

<div class="max-w-lg mx-auto drop-shadow-md rounded-lg lg:px-8">
    <div id="advanceContainer" class="rounded-lg bg-white pb-4 mt-3">
        <div class="bg-blue-500 rounded-t-lg  text-white font-bold mb-4 py-2 sticky top-0">
            <div class=" text-center italicized mb-4">

                {{ $event['band']['name'] }}
            </div>
            <div class="flex flex-row justify-around">
                <div>{{ $event->eventable->name }}</div>
                <div>{{ date('m/d/Y',strtotime($event->date)) }}</div>
            </div>
        </div>
        <div class="px-6 mb-2">
            <div class="-ml-2 font-bold">Venue:</div>
            <div>
                {{ $event->eventable->venue_name }}<br>
                {{ $street }}<br>
                {{ $city }}, {{ $state }} {{ $zip }}
            </div>
        </div>
        <div id="maps" class="flex sm:flex-row flex-col justify-center sm:justify-around px-6 mb-4">
            <div class="sm:max-w-xs p-4 flex flex-row sm:flex-col justify-around content-center items-center">
                <img src="/events/{{$event->key}}/locationImage" alt="googleMap" />

            </div>
            <div class="flex flex-row sm:flex-col justify-around content-center">
                <div style="max-width:60px">
                    <a href="https://www.google.com/maps/search/?api=1&query={{urlencode($event->eventable->venue_name . ' ' . $event->eventable->venue_address)}}">
                        <img src="/images/googlemaps.png" alt="googleMapsLink" />
                        <div class="text-xs whitespace-nowrap">Google Maps</div>
                    </a>
                </div>
                <div style="max-width:60px">
                    <a href="https://maps.apple.com/?q={{urlencode($event->eventable->venue_name . ' ' . $event->eventable->venue_address)}}">
                        <img src="/images/applemaps.png" alt="appleMapsLink" />
                        <div class="text-xs whitespace-nowrap">Apple Maps</div>
                    </a>
                </div>
            </div>
        </div>
        <div class="px-6 mb-4">
            <div class="-ml-2 font-bold">Schedule:</div>
            <div class="grid grid-cols-2 border">
                @php
                $additionalData = $event->additional_data;

                // Extract times from additional_data
                $additionalTimes = (array) ($additionalData->times ?? []);

                // Start with show_time as the only fixed time
                $times = [
                'show_time' => $event->time ?? null,
                ];

                // Add all additional times
                foreach ($additionalTimes as $key => $value) {
                $times[$key] = $value;
                }

                // Remove null values
                $times = array_filter($times);

                function formatTime($time) {
                if (is_string($time)) {
                return date('g:i A', strtotime($time));
                } elseif ($time instanceof DateTime) {
                return $time->format('g:i A');
                } elseif (is_object($time) && isset($time->date)) {
                return date('g:i A', strtotime($time->date));
                } else {
                return 'N/A';
                }
                }

                // Convert all times to timestamps for sorting
                $sortableTimes = array_map(function($time) {
                if (is_string($time)) {
                return strtotime($time);
                } elseif ($time instanceof DateTime) {
                return $time->getTimestamp();
                } elseif (is_object($time) && isset($time->date)) {
                return strtotime($time->date);
                } else {
                return PHP_INT_MAX; // Put invalid times at the end
                }
                }, $times);

                // Sort times
                asort($sortableTimes);

                // Prepare sorted times for display
                $sortedTimes = [];
                foreach ($sortableTimes as $key => $timestamp) {
                if ($timestamp !== PHP_INT_MAX) {
                $sortedTimes[$key] = $times[$key];
                }
                }
                @endphp

                @foreach ($sortedTimes as $key => $time)
                <div class="border text-center">{{ formatTime($time) }}</div>
                <div class="border px-2 {{ $key === 'show_time' ? 'font-bold' : '' }}">
                    @if ($key === 'ceremony_time')
                    {{ $additionalData->onsite ?? false ? 'Onsite ceremony' : 'Ceremony Offsite' }}
                    @else
                    {{ ucwords(str_replace('_', ' ', $key)) }}
                    @endif
                </div>
                @endforeach
            </div>
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
            <div class="bg-gray-100 p-4">{!! $event->notes !!}</div>
        </div>
    </div>
</div>
@stop