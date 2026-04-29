<?php

namespace App\Services;

use App\Models\Events;
use App\Models\QuestionnaireInstances;
use App\Models\QuestionnaireResponses;
use App\Models\User;
use RuntimeException;

class QuestionnaireMappingService
{
    public function __construct(private QuestionnaireMappingRegistry $registry)
    {
    }

    public function applyResponse(QuestionnaireResponses $response, User $appliedBy): Events
    {
        $field = $response->instanceField;
        if (!$field || !$field->mapping_target) {
            throw new RuntimeException('Field has no mapping target.');
        }

        $event = $this->resolveEvent($response->instance);
        $key = $field->mapping_target;

        if (!$this->registry->targetExists($key)) {
            throw new RuntimeException("Mapping target '{$key}' is no longer available.");
        }

        $kind = $this->registry->kind($key);

        if ($kind === QuestionnaireMappingRegistry::TYPE_BOOLEAN_PATH) {
            $this->writeBoolean($event, $this->registry->eventPath($key), $response->value);
        } elseif ($kind === QuestionnaireMappingRegistry::TYPE_DANCE_ENTRY) {
            $this->writeDance($event, $this->registry->danceTitle($key), (string) $response->value);
        }

        $event->save();

        $response->update([
            'applied_to_event_at' => now(),
            'applied_by_user_id' => $appliedBy->id,
        ]);

        return $event;
    }

    public function appendAllToNotes(QuestionnaireInstances $instance, User $appliedBy): Events
    {
        $event = $this->resolveEvent($instance);
        $event->notes = trim(($event->notes ?? '') . "\n\n" . $this->buildNotesBlock($instance));
        $event->save();

        return $event;
    }

    private function resolveEvent(QuestionnaireInstances $instance): Events
    {
        $event = $instance->booking->events()->orderBy('id')->first();
        if (!$event) {
            throw new RuntimeException('Booking has no event yet.');
        }
        return $event;
    }

    private function writeBoolean(Events $event, array $path, mixed $value): void
    {
        $bool = in_array(strtolower((string) $value), ['yes', 'true', '1', 'on'], true);
        // additional_data is cast as object; convert to array for data_set/data_get
        $data = json_decode(json_encode($event->additional_data ?? []), true);
        // Registry paths include 'additional_data' as the first segment; skip it
        // since we are already operating within additional_data
        $dotPath = implode('.', array_slice($path, 1));
        data_set($data, $dotPath, $bool);
        $event->additional_data = $data;
    }

    private function writeDance(Events $event, string $title, string $value): void
    {
        // additional_data is cast as object; convert to array for data_set/data_get
        $data = json_decode(json_encode($event->additional_data ?? []), true);
        $dances = data_get($data, 'wedding.dances', []);
        $dances = is_array($dances) ? $dances : [];

        $found = false;
        foreach ($dances as &$dance) {
            if (($dance['title'] ?? null) === $title) {
                $dance['data'] = $value;
                $found = true;
                break;
            }
        }
        unset($dance);

        if (!$found) {
            $dances[] = ['title' => $title, 'data' => $value];
        }

        data_set($data, 'wedding.dances', $dances);
        $event->additional_data = $data;
    }

    private function buildNotesBlock(QuestionnaireInstances $instance): string
    {
        $fields = $instance->fields()->orderBy('position')->get();
        $responses = $instance->responses()->get()->keyBy('instance_field_id');
        $date = now()->format('M j, Y');

        $songLookup = $this->buildSongLookup($instance, $fields, $responses);

        $lines = [];
        $lines[] = '----------';
        $lines[] = "Customer submitted \"{$instance->name}\" on {$date}";
        $lines[] = '';

        foreach ($fields as $f) {
            if ($f->type === 'instructions') {
                continue;
            }
            if ($f->type === 'header') {
                $lines[] = '';
                $lines[] = "== {$f->label} ==";
                continue;
            }
            $rawValue = $responses->get($f->id)?->value;

            if ($f->type === 'song_picker') {
                $lines[] = "{$f->label}:";
                foreach ($this->renderSongPickerLines($rawValue, $songLookup) as $songLine) {
                    $lines[] = "  - {$songLine}";
                }
                continue;
            }

            $value = $rawValue !== null && $rawValue !== '' ? $rawValue : '(not answered)';
            $decoded = json_decode((string) $value, true);
            if (is_array($decoded)) {
                $value = implode(', ', $decoded);
            }

            $lines[] = "{$f->label}: {$value}";
        }

        return implode("\n", $lines);
    }

    /**
     * Resolve song IDs referenced by song_picker responses to {title, artist}
     * tuples for the band that owns the instance's booking.
     *
     * @return array<int, array{title: string, artist: string|null}>
     */
    private function buildSongLookup(
        QuestionnaireInstances $instance,
        \Illuminate\Support\Collection $fields,
        \Illuminate\Support\Collection $responses,
    ): array {
        $songPickerFieldIds = $fields->where('type', 'song_picker')->pluck('id');
        if ($songPickerFieldIds->isEmpty()) {
            return [];
        }

        $songIds = collect();
        foreach ($responses as $r) {
            if (!$songPickerFieldIds->contains($r->instance_field_id)) {
                continue;
            }
            $decoded = json_decode((string) $r->value, true);
            if (is_array($decoded)) {
                $songIds = $songIds->merge($decoded);
            }
        }
        $songIds = $songIds->unique()->filter(fn ($id) => is_numeric($id))->values();

        if ($songIds->isEmpty()) {
            return [];
        }

        $bandId = $instance->booking->band_id;
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

    /**
     * @return array<int, string>
     */
    private function renderSongPickerLines(?string $rawValue, array $songLookup): array
    {
        if ($rawValue === null || $rawValue === '') {
            return ['(not answered)'];
        }
        $decoded = json_decode($rawValue, true);
        if (!is_array($decoded) || empty($decoded)) {
            return ['(none selected)'];
        }

        return collect($decoded)
            ->map(function ($id) use ($songLookup) {
                $song = $songLookup[$id] ?? null;
                if (!$song) {
                    return "(removed song #{$id})";
                }
                return $song['artist']
                    ? "{$song['title']} — {$song['artist']}"
                    : $song['title'];
            })
            ->values()
            ->all();
    }
}
