<?php

namespace App\Http\Requests\Mobile;

use Illuminate\Foundation\Http\FormRequest;

class StoreBookingEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Auth handled by middleware (auth:sanctum + mobile.band)
    }

    public function rules(): array
    {
        return [
            'title'         => 'required|string|max:255',
            'date'          => 'required|date',
            'start_time'    => 'nullable|date_format:H:i',
            'end_time'      => 'nullable|date_format:H:i',
            'venue_name'    => 'nullable|string|max:255',
            'venue_address' => 'nullable|string',
            'price'         => 'nullable|numeric|min:0',
            'event_type_id' => 'nullable|integer|exists:event_types,id',
            'notes'         => 'nullable|string',
        ];
    }
}
