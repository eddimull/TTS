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
            'contract_option' => [
                'sometimes', 'in:default,none,external',
                function ($attribute, $value, $fail)
                {
                    $contract = $this->route('booking')?->contract;
                    if ($contract && in_array($contract->status, ['sent', 'completed'], true))
                    {
                        $fail('The contract type cannot be changed after the contract has been sent.');
                    }
                },
            ],
            'deposit_type' => [
                'sometimes', 'required', 'in:percent,amount',
                new \App\Http\Requests\Rules\DepositNotLocked($this->route('booking')),
            ],
            'deposit_value' => [
                'sometimes', 'required', 'numeric', 'min:0',
                new \App\Http\Requests\Rules\DepositNotLocked($this->route('booking')),
                function ($attribute, $value, $fail) {
                    $type  = $this->input('deposit_type');
                    $price = $this->input('price') ?? optional($this->route('booking'))->price;
                    if ($type === 'percent' && (float) $value > 100) {
                        $fail('Deposit percent must be between 0 and 100.');
                    }
                    if ($type === 'amount' && (float) $value > (float) $price) {
                        $fail('Deposit amount cannot exceed the booking price.');
                    }
                },
            ],
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
