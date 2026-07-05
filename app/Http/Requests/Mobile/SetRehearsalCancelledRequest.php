<?php

namespace App\Http\Requests\Mobile;

use Illuminate\Foundation\Http\FormRequest;

class SetRehearsalCancelledRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Auth handled by middleware (auth:sanctum) + controller canWrite check
    }

    public function rules(): array
    {
        return [
            'is_cancelled' => ['required', 'boolean'],
        ];
    }
}
