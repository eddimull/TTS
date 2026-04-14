<?php

namespace App\Http\Requests\Mobile;

use Illuminate\Foundation\Http\FormRequest;

class TokenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Auth handled by middleware (auth:sanctum + mobile.band)
    }

    public function rules(): array
    {
        return [
            'email'       => 'required|email',
            'password'    => 'required|string',
            'device_name' => 'required|string|max:255',
        ];
    }
}
