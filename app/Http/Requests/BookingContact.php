<?php

namespace App\Http\Requests;

use App\Models\Bookings;
use FontLib\Table\Type\name;
use Illuminate\Foundation\Http\FormRequest;

class BookingContact extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize()
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
            'name' => 'required|string|max:255',
            'email' => 'required|email',
            'phone' => 'string|max:12',
            'notes' => 'nullable|string',
            'role' => 'nullable|string',
            'is_primary' => 'boolean',
            'additional_info' => 'nullable|string'
        ];
    }
}
