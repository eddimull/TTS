<?php

namespace App\Http\Requests\Mobile;

use Illuminate\Foundation\Http\FormRequest;

class StoreBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Auth handled by middleware (auth:sanctum + mobile.band)
    }

    public function rules(): array
    {
        return [
            'name'            => 'required|string|max:255',
            'event_type_id'   => 'required|integer|exists:event_types,id',
            'date'            => 'required|date',
            'start_time'      => 'required|date_format:H:i',
            'duration'        => 'required|numeric|min:0.5|max:24',
            'price'           => 'required|numeric|min:0',
            'venue_name'      => 'nullable|string|max:255',
            'venue_address'   => 'nullable|string',
            'contract_option' => 'nullable|in:default,none,external',
            'notes'           => 'nullable|string',
        ];
    }
}
