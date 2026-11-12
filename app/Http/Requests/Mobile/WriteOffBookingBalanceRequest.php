<?php

namespace App\Http\Requests\Mobile;

use Illuminate\Foundation\Http\FormRequest;

class WriteOffBookingBalanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Auth handled by middleware (auth:sanctum + mobile.band:write:bookings)
    }

    public function rules(): array
    {
        return [
            'notify_client' => 'sometimes|boolean',
            'note'          => 'nullable|string|max:255',
        ];
    }
}
