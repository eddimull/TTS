<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Shared by the mobile (`mobile.price-estimate`) and web (`bands.priceEstimate`)
 * estimate endpoints. Owner access is enforced by route middleware.
 */
class PriceEstimateRequest extends FormRequest
{
    private const TIME_REGEX = '/^\d{1,2}:\d{2}(:\d{2})?$/';

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'event_type_id' => ['required', 'integer', 'exists:event_types,id'],
            'date' => ['required', 'date_format:Y-m-d'],
            'start_time' => ['nullable', 'string', 'regex:' . self::TIME_REGEX],
            'end_time' => ['nullable', 'string', 'regex:' . self::TIME_REGEX],
            'duration_hours' => ['nullable', 'numeric', 'min:0.25', 'max:24'],
            'venue_address' => ['nullable', 'string', 'max:500'],
            'distance_miles' => ['nullable', 'numeric', 'min:0', 'max:10000'],
            'add_ons' => ['nullable', 'array'],
            'add_ons.*' => ['string', 'max:100'],
            // Optional: when estimating for an existing booking, don't flag
            // that booking's own date as a conflict.
            'exclude_booking_id' => ['nullable', 'integer'],
        ];
    }
}
