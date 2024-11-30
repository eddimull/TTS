<?php

namespace App\Http\Requests;

use App\Models\Bookings;
use Illuminate\Foundation\Http\FormRequest;

class UpdateContractsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $band = $this->route('band');
        return $this->user()->can('store', [Bookings::class, $band]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'custom_terms' => ['required', 'array', 'max:20'],
            'custom_terms.*.title' => ['required', 'string', 'min:3', 'max:255'],
            'custom_terms.*.content' => ['required', 'string', 'min:3'],
        ];
    }


    public function messages(): array
    {
        return [
            'custom_terms.required' => 'The custom terms field is required.',
            'custom_terms.array' => 'The custom terms must be an array.',
            'custom_terms.max' => 'You may not have more than :max custom terms.',
            'custom_terms.*.title.required' => 'Each custom term must have a title.',
            'custom_terms.*.title.string' => 'The custom term title must be a string.',
            'custom_terms.*.title.max' => 'The custom term title may not be greater than :max characters.',
            'custom_terms.*.content.required' => 'Each custom term must have content.',
            'custom_terms.*.content.string' => 'The custom term content must be a string.',
        ];
    }
}
