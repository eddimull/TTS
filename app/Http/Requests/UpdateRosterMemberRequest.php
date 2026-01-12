<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRosterMemberRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $rosterMember = $this->route('rosterMember');

        if (!$rosterMember) {
            return false;
        }

        // Only band owners can update roster members
        return $rosterMember->roster->band->owners()->where('user_id', $this->user()->id)->exists();
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'role' => ['nullable', 'string', 'max:100'],
            'default_payout_type' => ['sometimes', Rule::in(['equal_split', 'fixed', 'percentage'])],
            'default_payout_amount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'email.email' => 'Please provide a valid email address',
            'default_payout_type.in' => 'Payout type must be equal_split, fixed, or percentage',
            'default_payout_amount.min' => 'Payout amount cannot be negative',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Convert payout amount from dollars to cents if provided
        if ($this->has('default_payout_amount') && $this->default_payout_amount !== null) {
            $this->merge([
                'default_payout_amount' => (int) ($this->default_payout_amount * 100),
            ]);
        }
    }
}
