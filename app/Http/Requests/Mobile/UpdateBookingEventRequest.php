<?php

namespace App\Http\Requests\Mobile;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBookingEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Auth handled by middleware (auth:sanctum + mobile.band)
    }

    public function rules(): array
    {
        return [
            'title'         => 'sometimes|string|max:255',
            'date'          => 'sometimes|date',
            'start_time'    => 'sometimes|nullable|date_format:H:i',
            'end_time'      => 'sometimes|nullable|date_format:H:i',
            'venue_name'    => 'sometimes|nullable|string|max:255',
            'venue_address' => 'sometimes|nullable|string',
            'price'         => 'sometimes|nullable|numeric|min:0',
            'event_type_id' => 'sometimes|nullable|integer|exists:event_types,id',
            'notes'         => 'sometimes|nullable|string',
        ];
    }
}
