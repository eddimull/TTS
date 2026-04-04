<?php

namespace App\Http\Requests\Mobile;

use Illuminate\Foundation\Http\FormRequest;

class StoreChartRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Auth handled by middleware (auth:sanctum + mobile.band)
    }

    public function rules(): array
    {
        return [
            'title'       => 'required|string|max:255',
            'composer'    => 'nullable|string|max:255',
            'description' => 'nullable|string|max:2000',
            'price'       => 'nullable|numeric|min:0',
            'is_public'   => 'nullable|boolean',
        ];
    }
}
