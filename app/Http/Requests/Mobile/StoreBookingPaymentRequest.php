<?php

namespace App\Http\Requests\Mobile;

use Illuminate\Foundation\Http\FormRequest;

class StoreBookingPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Auth handled by middleware (auth:sanctum + mobile.band)
    }

    public function rules(): array
    {
        return [
            'name'         => 'required|string|max:255',
            'amount'       => 'required|numeric|min:0.01',
            'date'         => 'required|date',
            'payment_type' => 'required|in:cash,check,portal,venmo,zelle,invoice,wire,credit_card,other',
            'status'       => 'nullable|in:paid,pending',
        ];
    }
}
