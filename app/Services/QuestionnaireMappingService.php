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

        $html = "<hr>\n<p><strong>Customer submitted \"" . htmlspecialchars($instance->name, ENT_COMPAT | ENT_HTML5) . "\" on {$date}</strong></p>\n<ul>\n";

        foreach ($fields as $f) {
            if ($f->type === 'instructions') {
                continue;
            }
            if ($f->type === 'header') {
                $html .= "</ul>\n<h4>" . htmlspecialchars($f->label, ENT_COMPAT | ENT_HTML5) . "</h4>\n<ul>\n";
                continue;
            }
            $value = $responses->get($f->id)?->value;
            $value = $value !== null && $value !== '' ? $value : '(not answered)';
            // Decode JSON-encoded multi-values for display
            $decoded = json_decode((string) $value, true);
            if (is_array($decoded)) {
                $value = implode(', ', $decoded);
            }
            $html .= '<li><strong>' . htmlspecialchars($f->label, ENT_COMPAT | ENT_HTML5) . ':</strong> ' . htmlspecialchars((string) $value, ENT_COMPAT | ENT_HTML5) . "</li>\n";
        }

        $html .= '</ul>';
        return $html;
    }
}
