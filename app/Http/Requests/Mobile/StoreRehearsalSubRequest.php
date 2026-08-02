<?php

namespace App\Http\Requests\Mobile;

use Illuminate\Foundation\Http\FormRequest;

class StoreRehearsalSubRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // canWrite check happens in the controller
    }

    public function rules(): array
    {
        return [
            'call_list_entry_id' => ['nullable', 'required_without:email', 'integer'],
            'name'  => ['nullable', 'required_without:call_list_entry_id', 'string', 'max:255'],
            'email' => ['nullable', 'required_without:call_list_entry_id', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'band_role_id' => ['nullable', 'integer', 'exists:band_roles,id'],
        ];
    }
}
