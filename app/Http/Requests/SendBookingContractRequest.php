<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SendBookingContractRequest extends FormRequest
{
    public function authorize()
    {
        return true; // Adjust based on your authorization needs
    }

    public function rules()
    {
        return [
            'signer' => 'required|exists:contacts,id',
            'cc' => 'nullable|array',
            'cc.*' => 'exists:contacts,id',
        ];
    }

    public function messages()
    {
        return [
            'signer.required' => 'A signer contact is required.',
            'signer.exists' => 'The selected signer is not valid.',
            'cc.array' => 'CC must be an array of contact IDs.',
            'cc.*.exists' => 'One or more CC contacts are not valid.',
        ];
    }
}
