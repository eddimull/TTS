<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\Bookings;
use App\Models\EventTypes;

class UpdateBookingsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        $band = $this->route('band');
        return $this->user()->can('store', [Bookings::class, $band]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'name' => 'required|string|max:255',
            'author_id' => 'exclude',
            'event_type_id' => 'required|in:' . implode(',', EventTypes::all()->pluck('id')->toArray()),
            'date' => 'required|date',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i',
            'price' => [
                'required',
                'regex:/^\d+(\.\d{1,2})?$/',
                'min:0',
                'decimal:0,2'
            ],
            'venue_name' => 'nullable|string|max:255',
            'venue_address' => 'nullable|string',
            'contract_option' => 'required|in:default,none,external',
            'status' => 'nullable|in:draft,pending,confirmed,cancelled',
            'notes' => 'nullable|string',
        ];
    }

    public function messages()
    {
        return [
            'event_type.in' => 'The selected event type is invalid.',
            'time.date_format' => 'The time must be in the format HH:MM.',
            'price.min' => 'The price must be at least $0.',
        ];
    }
}
