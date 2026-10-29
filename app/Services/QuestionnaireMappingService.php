<?php

namespace App\Services;

use App\Models\Events;
use App\Models\QuestionnaireInstanceFields;
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

        $this->writeTarget($event, $key, $field, $response->value);

        $event->save();

        $response->update([
            'applied_to_event_at' => now(),
            'applied_by_user_id' => $appliedBy->id,
            'applied_value_hash' => self::valueHash($response->value),
        ]);

        return $event;
    }

    public function appendAllToNotes(QuestionnaireInstances $instance, User $appliedBy): Events
    {
        $event = $this->resolveEvent($instance);
        // events.notes is rendered with v-html; strip client-supplied markup.
        $event->notes = trim(($event->notes ?? '') . "\n\n" . strip_tags($this->buildNotesBlock($instance)));
        $event->save();

        return $event;
    }

    public function resolveEvent(QuestionnaireInstances $instance): Events
    {
        $event = $instance->booking->events()->orderBy('id')->first();
        if (!$event) {
            throw new RuntimeException('Booking has no event yet.');
        }
        return $event;
    }

    /**
     * Fingerprint of an answer at the moment it was sent, so an edit made
     * in the same second still reads back as stale.
     */
    public static function valueHash(?string $value): string
    {
        return hash('sha256', (string) $value);
    }

    /**
     * Write one answer to the event for the given target. Mutates $event in
     * memory; the caller saves. Throws when the target is unknown.
     */
    public function writeTarget(Events $event, string $key, QuestionnaireInstanceFields $field, ?string $rawValue): void
    {
        if (!$this->registry->targetExists($key)) {
            throw new RuntimeException("Mapping target '{$key}' is no longer available.");
        }

        $path = $this->registry->eventPath($key);

        match ($this->registry->kind($key)) {
            QuestionnaireMappingRegistry::TYPE_BOOLEAN_PATH => $this->writeBoolean($event, $path, $rawValue),
            QuestionnaireMappingRegistry::TYPE_DANCE_ENTRY => $this->writeDance(
                $event,
                $this->registry->danceTitle($key),
                $this->displayValue($rawValue, $field->type),
            ),
            QuestionnaireMappingRegistry::TYPE_TEXT_SET => $this->writeTextSet(
                $event,
                $path,
                $this->displayValue($rawValue, $field->type),
            ),
            QuestionnaireMappingRegistry::TYPE_TEXT_APPEND => $this->writeTextAppend(
                $event,
                $path,
                $this->registry->prefixesLabel($key)
                    ? "{$field->label}: {$this->displayValue($rawValue, $field->type)}"
                    : $this->displayValue($rawValue, $field->type),
            ),
            default => throw new RuntimeException("Mapping target '{$key}' has an unsupported kind."),
        };
    }

    /**
     * An answer as plain text: multi-value answers joined with ", ", yes/no
     * as "Yes"/"No", everything else trimmed.
     */
    public function displayValue(?string $rawValue, ?string $fieldType = null): string
    {
        if ($rawValue === null) {
            return '';
        }
        if ($fieldType === 'yes_no') {
            $lower = strtolower(trim($rawValue));
            if (in_array($lower, ['yes', 'true', '1', 'on'], true)) {
                return 'Yes';
            }
            if (in_array($lower, ['no', 'false', '0', 'off'], true)) {
                return 'No';
            }
        }
        $decoded = json_decode($rawValue, true);
        if (is_array($decoded)) {
            return implode(', ', array_map(fn ($v) => is_scalar($v) ? (string) $v : json_encode($v), $decoded));
        }
        return trim($rawValue);
    }

    /**
     * Overwrite a string at $path. Paths starting with 'additional_data'
     * write inside the JSON column; any other single segment is a column.
     */
    public function writeTextSet(Events $event, array $path, string $value): void
    {
        $this->setPath($event, $path, $value);
    }

    /**
     * Append $value as a new line at $path, unless that exact text is
     * already there as its own line(s).
     */
    public function writeTextAppend(Events $event, array $path, string $value): void
    {
        // Both append targets (events.notes, performance.notes) are rendered
        // with v-html on the web; strip markup so a client answer can't
        // inject tags or event handlers.
        $value = trim(str_replace("\r\n", "\n", strip_tags($value)));
        if ($value === '') {
            return;
        }

        $existing = (string) ($this->getPath($event, $path) ?? '');
        $normalized = str_replace("\r\n", "\n", $existing);
        if (preg_match('/(^|\n)' . preg_quote($value, '/') . '[ \t]*(\n|$)/', $normalized) === 1) {
            return;
        }

        $this->setPath($event, $path, trim($existing) === '' ? $value : rtrim($existing) . "\n" . $value);
    }

    private function getPath(Events $event, array $path): mixed
    {
        if (($path[0] ?? null) !== 'additional_data') {
            return $event->getAttribute($path[0]);
        }
        $data = json_decode(json_encode($event->additional_data ?? []), true);
        return data_get($data, implode('.', array_slice($path, 1)));
    }

    private function setPath(Events $event, array $path, mixed $value): void
    {
        if (($path[0] ?? null) !== 'additional_data') {
            $event->setAttribute($path[0], $value);
            return;
        }
        $data = json_decode(json_encode($event->additional_data ?? []), true);
        data_set($data, implode('.', array_slice($path, 1)), $value);
        $event->additional_data = $data;
    }

    public function writeBoolean(Events $event, array $path, mixed $value): void
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

    public function writeDance(Events $event, string $title, string $value): void
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
