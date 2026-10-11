<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Partial update of a band's price-estimator settings. Every key under
 * `settings` is optional; provided keys replace the stored value.
 */
class UpdatePricingSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'settings' => ['required', 'array'],

            'settings.season_multipliers' => ['sometimes', 'array'],
            'settings.season_multipliers.*' => ['numeric', 'min:0', 'max:10'],
            'settings.day_of_week_multipliers' => ['sometimes', 'array'],
            'settings.day_of_week_multipliers.*' => ['numeric', 'min:0', 'max:10'],

            'settings.special_dates' => ['sometimes', 'array', 'max:100'],
            'settings.special_dates.*.date' => ['required', 'string', 'regex:/^(\d{4}-)?(0[1-9]|1[0-2])-(0[1-9]|[12]\d|3[01])$/'],
            'settings.special_dates.*.label' => ['nullable', 'string', 'max:100'],
            'settings.special_dates.*.multiplier' => ['required', 'numeric', 'min:0', 'max:10'],

            'settings.standard_hours' => ['sometimes', 'numeric', 'min:0.5', 'max:24'],
            'settings.standard_hours_by_type' => ['sometimes', 'array'],
            'settings.standard_hours_by_type.*' => ['nullable', 'numeric', 'min:0.5', 'max:24'],
            'settings.overtime_pct_per_hour' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'settings.min_duration_factor' => ['sometimes', 'numeric', 'min:0', 'max:1'],

            'settings.short_notice_days' => ['sometimes', 'integer', 'min:0', 'max:365'],
            'settings.short_notice_multiplier' => ['sometimes', 'numeric', 'min:0', 'max:10'],
            'settings.annual_increase_pct' => ['sometimes', 'numeric', 'min:-50', 'max:100'],

            'settings.free_radius_miles' => ['sometimes', 'numeric', 'min:0', 'max:10000'],
            'settings.per_mile_rate' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'settings.lodging_threshold_miles' => ['sometimes', 'numeric', 'min:0', 'max:10000'],
            'settings.lodging_fee' => ['sometimes', 'numeric', 'min:0', 'max:1000000'],

            'settings.add_ons' => ['sometimes', 'array', 'max:50'],
            'settings.add_ons.*.key' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9_-]+$/', 'distinct'],
            'settings.add_ons.*.label' => ['required', 'string', 'max:100'],
            'settings.add_ons.*.amount' => ['required', 'numeric', 'min:0', 'max:1000000'],

            'settings.base_rates' => ['sometimes', 'array'],
            'settings.base_rates.*' => ['nullable', 'numeric', 'min:0', 'max:10000000'],

            'settings.round_to' => ['sometimes', 'numeric', 'min:1', 'max:10000'],
        ];
    }
}
