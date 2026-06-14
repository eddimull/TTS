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

    public function destroy(Request $request, string $token): JsonResponse
    {
        DeviceToken::where('user_id', $request->user()->id)
            ->where('token', $token)
            ->delete();

        return response()->json(['status' => 'ok']);
    }
}
