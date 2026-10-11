<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Requests\PriceEstimateRequest;
use App\Http\Requests\UpdatePricingSettingsRequest;
use App\Models\Bands;
use App\Services\PriceEstimatorService;
use Illuminate\Http\JsonResponse;

/**
 * Booking price estimator + pricing settings (owner-only).
 */
class PricingController extends Controller
{
    public function __construct(
        private PriceEstimatorService $estimator
    ) {}

    public function estimate(PriceEstimateRequest $request, Bands $band): JsonResponse
    {
        return response()->json($this->estimator->estimate($band, $request->validated()), 200, [], JSON_PRESERVE_ZERO_FRACTION);
    }

    public function showSettings(Bands $band): JsonResponse
    {
        return response()->json($this->estimator->settingsPayload($band), 200, [], JSON_PRESERVE_ZERO_FRACTION);
    }

    public function updateSettings(UpdatePricingSettingsRequest $request, Bands $band): JsonResponse
    {
        $this->estimator->saveSettings($band, $request->validated()['settings']);

        return response()->json($this->estimator->settingsPayload($band), 200, [], JSON_PRESERVE_ZERO_FRACTION);
    }
}
