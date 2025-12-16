<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use GoogleMaps\GoogleMaps;

class LocationController extends Controller
{
    protected $googleMaps;

    public function __construct(GoogleMaps $googleMaps)
    {
        $this->googleMaps = $googleMaps;
    }

    public function searchLocations(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'input' => 'required|string|min:4|max:255', // Increased min from 3 to 4
            'sessionToken' => 'required|numeric',
            'offset' => 'sometimes|integer|min:0',
            'location' => 'sometimes|string',
            'radius' => 'sometimes|integer|min:1',
            'language' => 'sometimes|string|size:2',
            'types' => 'sometimes|string',
            'components' => 'sometimes|string',
        ]);

        if ($validator->fails())
        {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try
        {
            $input = $request->input('input');

            // Check cache first for similar venues
            $cachedResults = DB::table('venue_cache')
                ->where(function($query) use ($input) {
                    $query->where('name', 'LIKE', "%{$input}%")
                          ->orWhere('formatted_address', 'LIKE', "%{$input}%");
                })
                ->orderBy('usage_count', 'desc')
                ->orderBy('last_used_at', 'desc')
                ->limit(5)
                ->get();

            if ($cachedResults->isNotEmpty())
            {
                // Return cached results in Google Places format
                return response()->json([
                    'predictions' => $cachedResults->map(function($venue) {
                        return [
                            'place_id' => $venue->place_id,
                            'description' => $venue->formatted_address,
                            'structured_formatting' => [
                                'main_text' => $venue->name,
                                'secondary_text' => $venue->formatted_address,
                            ],
                            'from_cache' => true
                        ];
                    })->toArray(),
                    'status' => 'OK'
                ]);
            }

            // If not in cache, call Google API
            $params = $request->only([
                'input',
                'sessionToken',
                'offset',
                'location',
                'radius',
                'language',
                'types',
                'components'
            ]);

            $response = $this->googleMaps->load('placeautocomplete')
                ->setParam($params)
                ->get();

            return response()->json(json_decode($response));
        }
        catch (\Exception $e)
        {
            return response()->json(['error' => 'Failed to fetch locations'], 500);
        }
    }

    public function getLocationDetails(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'place_id' => 'required|string',
            'sessionToken' => 'required|numeric',
            'language' => 'sometimes|string|size:2',
            'fields' => 'sometimes|string',
        ]);

        if ($validator->fails())
        {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try
        {
            $placeId = $request->input('place_id');

            // Check cache first
            $cached = DB::table('venue_cache')
                ->where('place_id', $placeId)
                ->first();

            if ($cached)
            {
                // Update usage statistics
                DB::table('venue_cache')
                    ->where('place_id', $placeId)
                    ->increment('usage_count');

                DB::table('venue_cache')
                    ->where('place_id', $placeId)
                    ->update(['last_used_at' => now()]);

                // Return cached data in Google Places format
                return response()->json([
                    'result' => json_decode($cached->raw_data),
                    'status' => 'OK',
                    'from_cache' => true
                ]);
            }

            // If not cached, call Google API
            $params = $request->only([
                'place_id',
                'sessiontoken',
                'language',
            ]);

            // Only request necessary fields to reduce API costs
            $params['fields'] = 'name,formatted_address,address_components,geometry,place_id';

            $response = $this->googleMaps->load('placedetails')
                ->setParam($params)
                ->get();

            $data = json_decode($response);

            // Cache the result if successful
            if (isset($data->result) && $data->status === 'OK')
            {
                $this->cacheVenueDetails($placeId, $data->result);
            }

            return response()->json($data);
        }
        catch (\Exception $e)
        {
            return response()->json(['error' => 'Failed to fetch location details'], 500);
        }
    }

    /**
     * Cache venue details to database
     */
    private function cacheVenueDetails($placeId, $result)
    {
        try
        {
            DB::table('venue_cache')->updateOrInsert(
                ['place_id' => $placeId],
                [
                    'name' => $result->name ?? '',
                    'formatted_address' => $result->formatted_address ?? '',
                    'street_address' => $this->extractAddressComponent($result, 'street_address'),
                    'city' => $this->extractAddressComponent($result, 'locality'),
                    'state' => $this->extractAddressComponent($result, 'administrative_area_level_1'),
                    'zip' => $this->extractAddressComponent($result, 'postal_code'),
                    'latitude' => $result->geometry->location->lat ?? null,
                    'longitude' => $result->geometry->location->lng ?? null,
                    'raw_data' => json_encode($result),
                    'last_used_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
        catch (\Exception $e)
        {
            // Log error but don't fail the request
            \Log::warning('Failed to cache venue details: ' . $e->getMessage());
        }
    }

    /**
     * Extract address component from Google Places result
     */
    private function extractAddressComponent($result, $type)
    {
        if (!isset($result->address_components)) {
            return null;
        }

        foreach ($result->address_components as $component)
        {
            if (in_array($type, $component->types))
            {
                return $component->long_name;
            }

            // Handle street_address specially
            if ($type === 'street_address')
            {
                $streetNumber = null;
                $route = null;

                foreach ($result->address_components as $comp)
                {
                    if (in_array('street_number', $comp->types))
                    {
                        $streetNumber = $comp->long_name;
                    }
                    if (in_array('route', $comp->types))
                    {
                        $route = $comp->long_name;
                    }
                }

                if ($streetNumber && $route)
                {
                    return $streetNumber . ' ' . $route;
                }
            }
        }

        return null;
    }

    /**
     * Geocode an address to lat/lng coordinates (with caching)
     */
    public function geocodeAddress(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'address' => 'required|string|min:4|max:500',
        ]);

        if ($validator->fails())
        {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try
        {
            $address = $request->input('address');

            // Check cache first
            $cached = DB::table('venue_cache')
                ->where('address', $address)
                ->first();

            if ($cached && $cached->latitude && $cached->longitude)
            {
                // Update usage statistics
                DB::table('venue_cache')
                    ->where('id', $cached->id)
                    ->increment('usage_count');

                DB::table('venue_cache')
                    ->where('id', $cached->id)
                    ->update(['last_used_at' => now()]);

                return response()->json([
                    'lat' => (float) $cached->latitude,
                    'lng' => (float) $cached->longitude,
                    'formatted_address' => $cached->formatted_address ?? $address,
                    'from_cache' => true
                ]);
            }

            // If not cached, call Google Geocoding API
            $response = $this->googleMaps->load('geocoding')
                ->setParamByKey('address', $address)
                ->get();

            $data = json_decode($response);

            if (isset($data->results[0]) && $data->status === 'OK')
            {
                $result = $data->results[0];
                $lat = $result->geometry->location->lat;
                $lng = $result->geometry->location->lng;

                // Cache the result
                DB::table('venue_cache')->insert([
                    'address' => $address,
                    'formatted_address' => $result->formatted_address ?? $address,
                    'latitude' => $lat,
                    'longitude' => $lng,
                    'raw_data' => json_encode($result),
                    'last_used_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                return response()->json([
                    'lat' => $lat,
                    'lng' => $lng,
                    'formatted_address' => $result->formatted_address ?? $address,
                    'from_cache' => false
                ]);
            }

            return response()->json(['error' => 'Geocoding failed'], 404);
        }
        catch (\Exception $e)
        {
            \Log::error('Geocoding failed: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to geocode address'], 500);
        }
    }
}
