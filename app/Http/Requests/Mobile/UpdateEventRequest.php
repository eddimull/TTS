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
            'time'                   => 'sometimes|nullable|date_format:H:i',
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
            'wedding'                => 'sometimes|array',
            'wedding.onsite'         => 'sometimes|nullable|boolean',
            'wedding.dances'         => 'sometimes|array',
            'wedding.dances.*.title' => 'required_with:wedding.dances|string|max:100',
            'wedding.dances.*.data'  => 'nullable|string|max:255',
        ];
    }
}
