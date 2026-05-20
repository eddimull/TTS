<?php

namespace App\Http\Requests\Mobile;

use Illuminate\Foundation\Http\FormRequest;

class StoreAttireChipRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Auth handled by middleware (auth:sanctum + mobile.band:write:events).
        return true;
    }

    /**
     * Normalize the incoming label before validation: trim outer whitespace
     * and collapse any internal runs of whitespace to a single space. The
     * controller still does a case-insensitive lookup against existing rows
     * to dedupe idempotently.
     */
    protected function prepareForValidation(): void
    {
        $label = $this->input('label');
        if (is_string($label)) {
            $label = trim(preg_replace('/\s+/u', ' ', $label));
            $this->merge(['label' => $label]);
        }
    }

    public function rules(): array
    {
        return [
            'label' => 'required|string|min:1|max:64',
        ];
    }
}
