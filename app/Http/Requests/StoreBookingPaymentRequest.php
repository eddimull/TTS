<?php

namespace App\Http\Requests;

use App\Models\Bookings;
use Illuminate\Foundation\Http\FormRequest;

class StoreBookingPaymentRequest extends FormRequest
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
            'band_id' => 'required|exists:bands,id',
            'user_id' => 'required|exists:users,id',
            'amount' => 'required|numeric|min:1',
            'date' => 'required|date',
        ];
    }

    protected function prepareForValidation()
    {
        $this->merge([
            'user_id' => $this->user()->id,
            'band_id' => $this->route('band')->id,
        ]);
    }
}
