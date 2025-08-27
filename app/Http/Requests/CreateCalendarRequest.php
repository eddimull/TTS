<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateCalendarRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // Remove this rule since type comes from URL, not request body
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $band = $this->route('band');
            $type = $this->route('type'); // Get type from URL parameter
            
            // Validate the type parameter
            if (!in_array($type, ['booking', 'event', 'public'])) {
                $validator->errors()->add('type', 'Invalid calendar type.');
                return;
            }
            
            if (!empty($band->calendars()->where('type', $type)->first())) {
                $validator->errors()->add('calendar', 'This band already has a calendar. Calendar ID: ' . $band->calendar_id);
            }
        });
    }
}
