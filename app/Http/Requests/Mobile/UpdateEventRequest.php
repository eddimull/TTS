<?php

namespace App\Http\Requests\Mobile;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Auth handled by middleware (auth:sanctum + mobile.band)
    }

    public function rules(): array
    {
        return [
            'title'                  => 'sometimes|string|max:255',
            'date'                   => 'sometimes|date_format:Y-m-d',
            // start_time / end_time replaced the legacy single `time` column
            // in the 2026-05-03 events-table migration. The mobile app sends
            // these as "HH:mm" strings.
            'start_time'             => 'sometimes|nullable|date_format:H:i',
            'end_time'               => 'sometimes|nullable|date_format:H:i',
            'notes'                  => 'sometimes|nullable|string',
            'venue_name'             => 'sometimes|nullable|string|max:255',
            'venue_address'          => 'sometimes|nullable|string|max:255',
            'attire'                 => 'sometimes|nullable|string|max:255',
            'is_public'              => 'sometimes|boolean',
            'outside'                => 'sometimes|boolean',
            'backline_provided'      => 'sometimes|boolean',
            'production_needed'      => 'sometimes|boolean',
            'timeline'               => 'sometimes|array',
            'timeline.*.title'       => 'required_with:timeline|string|max:255',
            'timeline.*.time'        => 'nullable|string|max:20',
            // Lodging is stored as a list of {title, type, data} rows in
            // additional_data.lodging. `data` is mixed — bool for the
            // "Provided" checkbox row, string for the text rows.
            'lodging'                => 'sometimes|array',
            'lodging.*.title'        => 'required_with:lodging|string|max:50',
            'lodging.*.type'         => 'required_with:lodging|in:checkbox,text',
            'lodging.*.data'         => 'nullable',
            'wedding'                => 'sometimes|array',
            'wedding.onsite'         => 'sometimes|nullable|boolean',
            'wedding.dances'         => 'sometimes|array',
            'wedding.dances.*.title' => 'required_with:wedding.dances|string|max:100',
            'wedding.dances.*.data'  => 'nullable|string|max:255',
        ];
    }
}
