<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRosterMemberRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $roster = $this->route('roster');

        if (!$roster) {
            return false;
        }

        // Only band owners can add members to rosters
        return $roster->band->owners()->where('user_id', $this->user()->id)->exists();
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $rosterId = $this->route('roster')->id;

        return [
            'user_id' => [
                'nullable',
                'exists:users,id',
                Rule::unique('roster_members')->where(function ($query) use ($rosterId) {
                    return $query->where('roster_id', $rosterId);
                }),
            ],
            'name' => ['required_without:user_id', 'string', 'max:255'],
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
            'user_id.exists' => 'Selected user does not exist',
            'user_id.unique' => 'This user is already in the roster',
            'name.required_without' => 'Name is required when not selecting an existing user',
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
