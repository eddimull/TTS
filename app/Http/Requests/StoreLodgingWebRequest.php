<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreLodgingWebRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Controller checks canWrite('lodging', $band->id)
    }

    public function rules(): array
    {
        return [
            'name'                        => 'required|string|max:255',
            'address'                     => 'nullable|string|max:255',
            'latitude'                    => 'nullable|numeric|between:-90,90',
            'longitude'                   => 'nullable|numeric|between:-180,180',
            'check_in_at'                 => 'required|date_format:Y-m-d H:i:s',
            'check_out_at'                => 'required|date_format:Y-m-d H:i:s|after:check_in_at',
            'notes'                       => 'nullable|string',
            'booking_id'                  => 'nullable|integer|exists:bookings,id',
            'event_id'                    => 'nullable|integer|exists:events,id',
            'rooms'                       => 'sometimes|array',
            'rooms.*.label'               => 'required|string|max:255',
            'rooms.*.confirmation_number' => 'nullable|string|max:255',
            'rooms.*.notes'               => 'nullable|string',
        ];
    }
}
