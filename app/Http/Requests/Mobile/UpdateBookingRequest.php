<?php

namespace App\Http\Requests\Mobile;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Auth handled by middleware (auth:sanctum + mobile.band)
    }

    public function rules(): array
    {
        return [
            'name'          => 'sometimes|string|max:255',
            'event_type_id' => 'sometimes|integer|exists:event_types,id',
            'date'          => 'sometimes|date',
            'start_time'    => 'sometimes|nullable|date_format:H:i',
            'end_time'      => 'sometimes|nullable|date_format:H:i',
            'price'         => 'sometimes|nullable|numeric|min:0',
            'venue_name'    => 'sometimes|nullable|string|max:255',
            'venue_address' => 'sometimes|nullable|string',
            'notes'         => 'sometimes|nullable|string',
            'status'        => 'sometimes|in:draft,pending,confirmed,cancelled',
        ];
    }
}
