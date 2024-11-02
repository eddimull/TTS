<?php

namespace App\Http\Requests;

use Carbon\Carbon;
use App\Models\Bookings;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'time' => 'required',
            'title' => 'required|string',
            'notes' => 'nullable|string',
            'additional_data' => 'required|array',
            'additional_data.migrated_from_event_id' => 'nullable|integer',
            'additional_data.public' => 'required|boolean',
            'additional_data.outside' => 'required|boolean',
            'additional_data.lodging' => 'required|array',
            'additional_data.production_needed' => 'required|boolean',
            'additional_data.backline_provided' => 'required|boolean',
            'additional_data.attire' => 'nullable|string',
            'additional_data.times' => 'nullable|array',
            'additional_data.times.*.title' => 'required|string',
            'additional_data.times.*.time' => 'required|date_format:Y-m-d H:i',
            'additional_data.wedding' => 'nullable|array',
            'additional_data.wedding.onsite' => 'required_with:additional_data.wedding|boolean',
            'additional_data.wedding.dances' => 'nullable|array',
            'additional_data.wedding.dances.*.title' => 'required|string',
            'additional_data.wedding.dances.*.data' => 'nullable|string',
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
            $formattedTimes = collect($this->input('additional_data.times'))->map(function ($timeEntry)
            {
                if (isset($timeEntry['time']))
                {
                    $timeEntry['time'] = $this->formatDateTime($timeEntry['time']);
                }
                return $timeEntry;
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
            return Carbon::parse($dateTime)->format('Y-m-d H:i');
        }
        catch (\Exception $e)
        {
            return null;
        }
    }
}
