<?php

namespace App\Http\Requests\Mobile;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateChartRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Auth handled by middleware (auth:sanctum + mobile.band)
    }

    public function rules(): array
    {
        return [
            'title'       => 'sometimes|required|string|max:255',
            'composer'    => 'sometimes|nullable|string|max:255',
            'description' => 'sometimes|nullable|string|max:2000',
            'price'       => 'sometimes|nullable|numeric|min:0',
            'is_public'   => 'sometimes|nullable|boolean',
            'song_id'     => ['sometimes', 'nullable', 'integer',
                Rule::exists('songs', 'id')->where(fn ($q) => $q->where('band_id', $this->route('band')?->id)),
            ],
        ];
    }
}
