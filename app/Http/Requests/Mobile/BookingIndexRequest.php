<?php

namespace App\Http\Requests\Mobile;

use Illuminate\Foundation\Http\FormRequest;

class BookingIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Auth handled by middleware (auth:sanctum + mobile.band)
    }

    public function rules(): array
    {
        return [
            'status'   => 'nullable|string',
            'upcoming' => 'nullable|boolean',
            'year'     => 'nullable|integer|min:2000|max:2100',
        ];
    }
}
