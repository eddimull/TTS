<?php

namespace App\Http\Controllers;

use App\Http\Requests\PriceEstimateRequest;
use App\Http\Requests\UpdatePricingSettingsRequest;
use App\Models\Bands;
use App\Services\PriceEstimatorService;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Web (Inertia) side of the booking price estimator. All routes are
 * owner-only (route middleware). The JSON endpoints share request/response
 * shapes with the mobile API.
 */
class PriceEstimatorController extends Controller
{
    public function __construct(
        private PriceEstimatorService $estimator
    ) {}

    public function index(Bands $band): Response
    {
        $settings = $this->estimator->settingsFor($band);

        return Inertia::render('PriceEstimator/Index', [
            'band' => [
                'id' => $band->id,
                'name' => $band->name,
                'has_address' => (bool) ($band->address && $band->city && $band->state),
            ],
            'addOns' => $this->estimator->presentSettings($settings)['add_ons'],
            'standardHours' => (float) $settings['standard_hours'],
        ]);
    }

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
