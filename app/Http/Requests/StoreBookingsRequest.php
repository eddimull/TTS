<?php

namespace App\Http\Requests;

use App\Models\Bookings;
use Illuminate\Foundation\Http\FormRequest;
use App\Models\EventTypes;
use Carbon\Carbon;

class StoreBookingsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        $band = $this->route('band');
        return $this->user()->can('store', [Bookings::class, $band]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'author_id' => 'required|exists:users,id',
            'event_type_id' => 'required|in:' . implode(',', EventTypes::all()->pluck('id')->toArray()),
            'date' => 'required|date',
            'start_time' => 'required|date_format:H:i',
            'duration' => 'required|integer|min:1',
            'price' => 'required|numeric|min:0',
            'venue_name' => 'nullable|string|max:255',
            'venue_address' => 'nullable|string',
            'contract_option' => 'required|in:default,none,external',
            'status' => 'nullable|in:draft,pending,confirmed,cancelled',
            'notes' => 'nullable|string',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->mergeIfMissing([
            'end_time' => '00:00'
        ]);
        $this->merge([
            'author_id' => $this->user()->id
        ]);
    }

    public function messages(): array
    {
        return [
            'event_type.in' => 'The selected event type is invalid.',
            'time.date_format' => 'The time must be in the format HH:MM.',
            'duration.min' => 'The duration must be at least 1 hour. You can change this later if needed.',
            'price.min' => 'The price must be at least $0.',
        ];
    }

    public function validated($key = null, $default = null): mixed
    {
        $validated = parent::validated($key, $default);

        if (is_null($key) && is_null($default))
        {
            $startTime = Carbon::parse($validated['date'] . ' ' . $validated['start_time']);
            $endTime = $startTime->copy()->addHours($validated['duration']);

            $validated['end_time'] = $endTime->format('H:i');
            unset($validated['duration']);
        }

        return $validated;
    }
}
