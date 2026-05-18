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
            'price'           => 'nullable|numeric|min:0',
            'contract_option' => 'nullable|in:default,none,external',
            'notes'           => 'nullable|string',

            // Date / time / venue now live on events, not the booking. A
            // booking is created with one or more events; start_time is
            // required so the additional_data schedule can be anchored.
            'events'                 => 'required|array|min:1',
            'events.*.title'         => 'required|string|max:255',
            'events.*.date'          => 'required|date',
            'events.*.start_time'    => 'required|date_format:H:i',
            'events.*.end_time'      => 'nullable|date_format:H:i',
            'events.*.venue_name'    => 'nullable|string|max:255',
            'events.*.venue_address' => 'nullable|string',
            'events.*.price'         => 'nullable|numeric|min:0',

            'deposit_type'  => 'sometimes|required|in:percent,amount',
            'deposit_value' => [
                'sometimes', 'required', 'numeric', 'min:0',
                function ($attribute, $value, $fail) {
                    $type = $this->input('deposit_type');
                    if ($type === 'percent' && (float) $value > 100) {
                        $fail('Deposit percent must be between 0 and 100.');
                    }
                    if ($type === 'amount' && (float) $value > (float) $this->input('price')) {
                        $fail('Deposit amount cannot exceed the booking price.');
                    }
                },
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'events.required'              => 'A booking must include at least one event.',
            'events.min'                   => 'A booking must include at least one event.',
            'events.*.start_time.required' => 'Each event needs a start time.',
        ];
    }
}
