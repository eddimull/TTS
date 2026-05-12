<?php

namespace App\Http\Requests\Mobile;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBookingContractTermsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Auth handled by middleware (auth:sanctum + mobile.band)
    }

    public function rules(): array
    {
        return [
            'custom_terms'           => ['required', 'array'],
            'custom_terms.*.title'   => ['nullable', 'string', 'max:255'],
            'custom_terms.*.content' => ['nullable', 'string'],
        ];
    }
}
