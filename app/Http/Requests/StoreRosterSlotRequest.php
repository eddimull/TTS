<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreRosterSlotRequest extends FormRequest
{
    public function authorize(): bool
    {
        $roster = $this->route('roster');

        return $roster && $roster->band->owners()->where('user_id', $this->user()->id)->exists();
    }

    public function rules(): array
    {
        return [
            'name'         => ['required', 'string', 'max:255'],
            'band_role_id' => ['nullable', 'exists:band_roles,id'],
            'is_required'  => ['boolean'],
            'quantity'     => ['integer', 'min:1', 'max:99'],
            'notes'        => ['nullable', 'string', 'max:1000'],
        ];
    }
}
