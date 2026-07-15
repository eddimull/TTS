<?php

namespace App\Services;

use App\Models\Questionnaires;
use Illuminate\Validation\ValidationException;

class QuestionnaireTemplateService
{
    public function __construct(
        private QuestionnaireFieldTypeRegistry $typeRegistry,
        private QuestionnaireMappingRegistry $mappingRegistry,
        private FieldSettingsValidator $settingsValidator,
        private QuestionnairePresetRegistry $presetRegistry,
    ) {
    }

    /** Clone a preset's field definitions onto a freshly created questionnaire. No-op when preset unknown. */
    public function applyPreset(Questionnaires $questionnaire, string $presetKey): void
    {
        if (!$this->presetRegistry->exists($presetKey)) {
            return;
        }

        $preset = $this->presetRegistry->get($presetKey);
        $position = 10;
        foreach ($preset['fields'] as $field) {
            $questionnaire->fields()->create([
                'type' => $field['type'],
                'label' => $field['label'],
                'help_text' => $field['help_text'] ?? null,
                'required' => $field['required'] ?? false,
                'position' => $position,
                'settings' => $field['settings'] ?? null,
                'mapping_target' => $field['mapping_target'] ?? null,
                'visibility_rule' => null,
            ]);
            $position += 10;
        }
    }

    /**
     * Combined custom validation: per-type settings, mapping-target compatibility,
     * forward-visibility check.
     *
     * @throws ValidationException
     */
    public function validateFieldsPayload(array $fields): void
    {
        $errors = [];

        // Position-by-client_id map for forward-reference detection
        $positionByClientId = [];
        foreach ($fields as $f) {
            $positionByClientId[$f['client_id']] = $f['position'] ?? PHP_INT_MAX;
        }

        foreach ($fields as $i => $f) {
            $type = $f['type'] ?? null;
            $settings = $f['settings'] ?? null;
            $rule = $f['visibility_rule'] ?? null;
            $mapping = $f['mapping_target'] ?? null;

            // Per-type settings shape
            $settingsErrors = $this->settingsValidator->validate($type, $settings);
            foreach ($settingsErrors as $err) {
                $errors["fields.{$i}.settings"][] = $err;
            }

            // Mapping-target compatibility
            if (!empty($mapping)) {
                $compatible = $this->mappingRegistry->compatibleFieldTypes($mapping);
                if (!in_array($type, $compatible, true)) {
                    $errors["fields.{$i}.mapping_target"][] =
                        "Field type '{$type}' is not compatible with mapping target '{$mapping}'.";
                }
            }

            // Forward-reference check
            if (!empty($rule['depends_on'])) {
                $thisPos = $f['position'] ?? PHP_INT_MAX;
                $depPos = $positionByClientId[$rule['depends_on']] ?? null;
                if ($depPos === null) {
                    $errors["fields.{$i}.visibility_rule.depends_on"][] =
                        "Visibility rule references unknown field '{$rule['depends_on']}'.";
                } elseif ($depPos >= $thisPos) {
                    $errors["fields.{$i}.visibility_rule.depends_on"][] =
                        "Visibility rule must reference a field that comes earlier in the questionnaire.";
                }
            }
        }

        if (!empty($errors)) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * Diff existing fields vs payload by id, upsert present, delete missing.
     * Two-pass: first upsert, then rewrite visibility_rule depends_on to use
     * permanent ids resolved from client_ids.
     *
     * Must run inside a caller-owned DB::transaction.
     */
    public function upsertFields(Questionnaires $questionnaire, array $fields): void
    {
        $payloadFields = $fields;

        $payloadIds = collect($payloadFields)->pluck('id')->filter()->all();
        $questionnaire->fields()->whereNotIn('id', $payloadIds)->delete();

        $clientIdToPersistedId = [];

        foreach ($payloadFields as $f) {
            $attributes = [
                'questionnaire_id' => $questionnaire->id,
                'type' => $f['type'],
                'label' => $f['label'],
                'help_text' => $f['help_text'] ?? null,
                'required' => $f['required'] ?? false,
                'position' => $f['position'],
                'settings' => $f['settings'] ?? null,
                'mapping_target' => $f['mapping_target'] ?? null,
                // visibility_rule rewritten in second pass
                'visibility_rule' => null,
            ];

            if (!empty($f['id'])) {
                $field = $questionnaire->fields()->find($f['id']);
                if ($field) {
                    $field->update($attributes);
                    $clientIdToPersistedId[$f['client_id']] = $field->id;
                    continue;
                }
            }

            $created = $questionnaire->fields()->create($attributes);
            $clientIdToPersistedId[$f['client_id']] = $created->id;
        }

        // Second pass: rewrite visibility_rule.depends_on
        foreach ($payloadFields as $f) {
            if (empty($f['visibility_rule']['depends_on'])) {
                continue;
            }
            $persistedId = $clientIdToPersistedId[$f['client_id']];
            $depClientId = $f['visibility_rule']['depends_on'];
            $depPersistedId = $clientIdToPersistedId[$depClientId] ?? null;
            if ($depPersistedId === null) {
                continue;
            }

            $rewritten = [
                'depends_on' => $depPersistedId,
                'operator' => $f['visibility_rule']['operator'],
                'value' => $f['visibility_rule']['value'] ?? null,
            ];
            $questionnaire->fields()->where('id', $persistedId)->update([
                'visibility_rule' => json_encode($rewritten),
            ]);
        }
    }
}
