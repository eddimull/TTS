<?php

namespace App\Http\Requests\Mobile;

use Illuminate\Foundation\Http\FormRequest;

class OffSetlistRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Auth handled by middleware (auth:sanctum + mobile.band)
    }

    public function rules(): array
    {
        return [
            // A catalog song, or a client request that isn't in the catalog.
            'song_id' => 'required_without:custom_title|nullable|integer|exists:songs,id',
            'custom_title' => 'required_without:song_id|nullable|string|max:255',
            'custom_artist' => 'nullable|string|max:255',
            // 'next' (default) plays it right after the current song;
            // 'end' appends it behind everything already queued.
            'placement' => 'sometimes|in:next,end',
        ];
    }
}
