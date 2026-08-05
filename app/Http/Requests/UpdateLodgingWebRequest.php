<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLodgingWebRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Controller checks canWrite('lodging', $lodging->band_id)
    }

    public function rules(): array
    {
        // PATCH semantics: everything optional, validated when present.
        return [
            'name'                        => 'sometimes|required|string|max:255',
            'address'                     => 'sometimes|nullable|string|max:255',
            'latitude'                    => 'sometimes|nullable|numeric|between:-90,90',
            'longitude'                   => 'sometimes|nullable|numeric|between:-180,180',
            'check_in_at'                 => 'sometimes|required|date_format:Y-m-d H:i:s',
            'check_out_at'                => 'sometimes|required|date_format:Y-m-d H:i:s',
            'notes'                       => 'sometimes|nullable|string',
            'booking_id'                  => 'sometimes|nullable|integer|exists:bookings,id',
            'event_id'                    => 'sometimes|nullable|integer|exists:events,id',
            'rooms'                       => 'sometimes|array',
            'rooms.*.id'                  => 'sometimes|integer',
            'rooms.*.label'               => 'required|string|max:255',
            'rooms.*.confirmation_number' => 'nullable|string|max:255',
            'rooms.*.notes'               => 'nullable|string',
        ];
    }
}
