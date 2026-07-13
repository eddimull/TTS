<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSongRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Permission enforced by songs.write / mobile.band middleware + controller checks.
    }

    /**
     * Mobile routes carry the band as a route param (/bands/{band}/songs);
     * web sends band_id in the body. Normalize so one rule set serves both.
     */
    protected function prepareForValidation(): void
    {
        $band = $this->route('band');

        if ($band !== null) {
            $this->merge(['band_id' => is_object($band) ? $band->id : $band]);
        }
    }

    /**
     * @return array<string, string>
     */
    public function rules(): array
    {
        return [
            'band_id' => 'required|integer|exists:bands,id',
            'title' => 'required|string|max:255',
            'artist' => 'nullable|string|max:255',
            'song_key' => 'nullable|string|max:20',
            'genre' => 'nullable|string|max:100',
            'bpm' => 'nullable|integer|min:1|max:999',
            'rating' => 'nullable|integer|min:1|max:10',
            'energy' => 'nullable|integer|min:1|max:10',
            'notes' => 'nullable|string',
            'lead_singer_id' => 'nullable|integer|exists:roster_members,id',
            'transition_song_id' => 'nullable|integer|exists:songs,id',
            'active' => 'boolean',
        ];
    }
}
