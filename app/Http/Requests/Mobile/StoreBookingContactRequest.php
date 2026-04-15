<?php

namespace App\Http\Requests\Mobile;

use Illuminate\Foundation\Http\FormRequest;

class StoreBookingContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Auth handled by middleware (auth:sanctum + mobile.band)
    }

    public function rules(): array
    {
        return [
            'contact_id' => 'nullable|integer|exists:contacts,id',
            'name'       => 'required_without:contact_id|string|max:255',
            'email'      => 'required_without:contact_id|email',
            'phone'      => 'nullable|string',
            'role'       => 'nullable|string|max:255',
            'is_primary' => 'boolean',
        ];
    }
}
