<?php

namespace App\Http\Requests;

use Carbon\Carbon;
use App\Models\Bookings;
use Illuminate\Foundation\Http\FormRequest;

class UpdateBookingEventRequest extends FormRequest
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
            'date' => 'required|date',
            'additional_data' => 'required|array',
            'additional_data.color' => 'nullable|string',
            'additional_data.times' => 'nullable|array',
            'additional_data.times.*' => 'nullable|date_format:Y-m-d H:i:s',
            'additional_data.dances' => 'nullable|array',
            'additional_data.dances.*' => 'nullable|string',
            'additional_data.onsite' => 'required|boolean',
            'additional_data.public' => 'required|boolean',
            'additional_data.lodging' => 'required|boolean',
            'additional_data.outside' => 'required|boolean',
            'additional_data.backline_provided' => 'required|boolean',
            'additional_data.production_needed' => 'required|boolean',
            'notes' => 'nullable|string',
            'title' => 'required|string',
            'time' => 'required',
        ];
    }

    /**
     * Prepare the data for validation.
     *
     * @return void
     */
    protected function prepareForValidation()
    {
        $this->formatDateTimes();
    }

    /**
     * Format the datetime fields.
     *
     * @return void
     */
    protected function formatDateTimes()
    {
        if ($this->has('additional_data.times'))
        {
            $formattedTimes = collect($this->input('additional_data.times'))->map(function ($time)
            {
                return $this->formatDateTime($time);
            })->filter()->all();

            $this->merge([
                'additional_data' => array_merge($this->input('additional_data'), ['times' => $formattedTimes])
            ]);
        }
    }

    /**
     * Format a single datetime string.
     *
     * @param string|null $dateTime
     * @return string|null
     */
    protected function formatDateTime($dateTime)
    {
        if (!$dateTime) return null;

        try
        {
            return Carbon::parse($dateTime)->format('Y-m-d H:i:s');
        }
        catch (\Exception $e)
        {
            return null;
        }
    }
}
