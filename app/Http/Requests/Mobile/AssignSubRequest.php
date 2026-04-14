<?php

namespace App\Http\Requests\Mobile;

use Illuminate\Foundation\Http\FormRequest;

class AssignSubRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Auth handled by middleware (auth:sanctum + mobile.band)
    }

    public function rules(): array
    {
        return [
            'clear'            => 'sometimes|boolean',
            'slot_id'          => 'sometimes|integer|exists:roster_slots,id',
            'roster_member_id' => 'sometimes|integer|exists:roster_members,id',
            'name'             => 'sometimes|string|max:255',
            'email'            => 'sometimes|nullable|email|max:255',
        ];
    }
}
