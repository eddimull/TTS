<?php

namespace App\Services;

class QuestionnaireResponsePresenter
{
    /**
     * Multi-value responses are JSON-encoded arrays. Decode for Vue.
     */
    public function decode(?string $value): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }
        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : $value;
    }

    /**
     * Multi-value field types (multi_select, checkbox_group) JSON-encode their array.
     * Other types coerce to string.
     */
    public function encode(mixed $value, string $type): ?string
    {
        if ($value === null) {
            return null;
        }
        if (in_array($type, ['multi_select', 'checkbox_group', 'song_picker'], true)) {
            return is_array($value) ? json_encode(array_values($value)) : json_encode([$value]);
        }
        return is_array($value) ? implode(',', $value) : (string) $value;
    }

    /**
     * Build a song-id => {title, artist} lookup for any song_picker
     * responses across the given instances. Removed songs appear with a
     * "(removed song #N)" placeholder title.
     *
     * @param  \Illuminate\Support\Collection<int, \App\Models\QuestionnaireInstances>  $instances
     */
    public function songLookup(iterable $instances, int $bandId): array
    {
        $songIds = collect();
        foreach ($instances as $instance) {
            $songPickerFieldIds = $instance->fields
                ->where('type', 'song_picker')
                ->pluck('id');

            foreach ($instance->responses as $response) {
                if (!$songPickerFieldIds->contains($response->instance_field_id)) {
                    continue;
                }
                $decoded = json_decode((string) $response->value, true);
                if (is_array($decoded)) {
                    $songIds = $songIds->merge($decoded);
                }
            }
        }
        $songIds = $songIds->unique()->filter(fn ($id) => is_numeric($id))->values();

        if ($songIds->isEmpty()) {
            return [];
        }

        $songs = \App\Models\Song::where('band_id', $bandId)
            ->whereIn('id', $songIds)
            ->get(['id', 'title', 'artist']);

        $lookup = [];
        foreach ($songs as $song) {
            $lookup[$song->id] = ['title' => $song->title, 'artist' => $song->artist];
        }
        foreach ($songIds as $id) {
            if (!isset($lookup[$id])) {
                $lookup[$id] = ['title' => "(removed song #{$id})", 'artist' => null];
            }
        }
        return $lookup;
    }
}
