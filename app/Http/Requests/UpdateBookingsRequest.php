<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\Bookings;
use App\Models\EventTypes;

class UpdateBookingsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        $band = $this->route('band');
        return $this->user()->can('store', [Bookings::class, $band]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'name' => 'required|string|max:255',
            'author_id' => 'exclude',
            'event_type_id' => 'required|in:' . implode(',', EventTypes::all()->pluck('id')->toArray()),
            'date'          => 'prohibited',
            'start_time'    => 'prohibited',
            'end_time'      => 'prohibited',
            'price' => [
                'required',
                'regex:/^\d+(\.\d{1,2})?$/',
                'min:0',
                'decimal:0,2'
            ],
            'venue_name'    => 'prohibited',
            'venue_address' => 'prohibited',
            'contract_option' => 'required|in:default,none,external',
            'status' => 'nullable|in:draft,pending,confirmed,cancelled',
            'notes' => 'nullable|string',
            'deposit_type' => [
                'sometimes', 'required', 'in:percent,amount',
                new \App\Http\Requests\Rules\DepositNotLocked($this->route('booking')),
            ],
            'deposit_value' => [
                'sometimes', 'required', 'numeric', 'min:0',
                'regex:/^\d+(\.\d{1,2})?$/',
                new \App\Http\Requests\Rules\DepositNotLocked($this->route('booking')),
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

    public function messages()
    {
        return [
            'event_type.in'            => 'The selected event type is invalid.',
            'time.date_format'         => 'The time must be in the format HH:MM.',
            'price.min'                => 'The price must be at least $0.',
            'date.prohibited'          => 'The date field has moved to events. Use POST /bookings/{id}/events or PATCH /events/{eventId} instead.',
            'start_time.prohibited'    => 'The start_time field has moved to events. Use the event subresource endpoints instead.',
            'end_time.prohibited'      => 'The end_time field has moved to events. Use the event subresource endpoints instead.',
            'venue_name.prohibited'    => 'The venue_name field has moved to events. Use the event subresource endpoints instead.',
            'venue_address.prohibited' => 'The venue_address field has moved to events. Use the event subresource endpoints instead.',
        ];
    }
}
