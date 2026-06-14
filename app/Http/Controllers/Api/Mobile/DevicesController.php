<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Requests\Mobile\StoreDeviceTokenRequest;
use App\Models\DeviceToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DevicesController extends Controller
{
    public function store(StoreDeviceTokenRequest $request): JsonResponse
    {
        DeviceToken::updateOrCreate(
            ['token' => $request->string('token')->toString()],
            [
                'user_id'  => $request->user()->id,
                'platform' => $request->string('platform')->toString(),
            ],
        );

        return response()->json(['status' => 'ok']);
    }

    public function destroy(Request $request): JsonResponse
    {
        // Token comes in the request body, not the URL path: FCM/APNs tokens can
        // contain '/' and ':' which would break a path-segment route binding.
        $request->validate(['token' => ['required', 'string', 'max:512']]);

        DeviceToken::where('user_id', $request->user()->id)
            ->where('token', $request->string('token')->toString())
            ->delete();

        return response()->json(['status' => 'ok']);
    }
}
