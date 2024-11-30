<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
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
            'input' => 'required|string|min:3|max:255',
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
            $params = $request->only([
                'place_id',
                'sessiontoken',
                'language',
                'fields'
            ]);

            $response = $this->googleMaps->load('placedetails')
                ->setParam($params)
                ->get();

            return response()->json(json_decode($response));
        }
        catch (\Exception $e)
        {
            return response()->json(['error' => 'Failed to fetch location details'], 500);
        }
    }
}
