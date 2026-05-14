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
            'price'           => 'nullable|numeric|min:0',
            'venue_name'      => 'nullable|string|max:255',
            'venue_address'   => 'nullable|string',
            'contract_option' => 'nullable|in:default,none,external',
            'notes'           => 'nullable|string',
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
}
