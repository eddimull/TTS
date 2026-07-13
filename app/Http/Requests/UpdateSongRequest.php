<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSongRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Permission enforced by songs.write / mobile.band middleware + controller checks.
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'artist' => 'nullable|string|max:255',
            'song_key' => 'nullable|string|max:20',
            'genre' => 'nullable|string|max:100',
            'bpm' => 'nullable|integer|min:1|max:999',
            'rating' => 'nullable|integer|min:1|max:10',
            'energy' => 'nullable|integer|min:1|max:10',
            'notes' => 'nullable|string',
            'lead_singer_id' => 'nullable|integer|exists:roster_members,id',
            'transition_song_id' => ['nullable', 'integer',
                Rule::exists('songs', 'id')->where(fn ($q) => $q->where('band_id', $this->route('song')?->band_id ?? $this->route('band')?->id)),
            ],
            'active' => 'boolean',
        ];
    }
}
