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
            'date'          => 'prohibited',
            'start_time'    => 'prohibited',
            'end_time'      => 'prohibited',
            'price'         => 'sometimes|nullable|numeric|min:0',
            'venue_name'    => 'prohibited',
            'venue_address' => 'prohibited',
            'notes'         => 'sometimes|nullable|string',
            'status'        => 'sometimes|in:draft,pending,confirmed,cancelled',
        ];
    }

    public function messages(): array
    {
        return [
            'date.prohibited'          => 'The date field has moved to events. Use POST /api/mobile/bands/{bandId}/bookings/{id}/events or PATCH .../events/{eventId} instead.',
            'start_time.prohibited'    => 'The start_time field has moved to events. Use the event subresource endpoints instead.',
            'end_time.prohibited'      => 'The end_time field has moved to events. Use the event subresource endpoints instead.',
            'venue_name.prohibited'    => 'The venue_name field has moved to events. Use the event subresource endpoints instead.',
            'venue_address.prohibited' => 'The venue_address field has moved to events. Use the event subresource endpoints instead.',
        ];
    }
}
