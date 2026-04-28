<?php

namespace App\Services;

class FieldSettingsValidator
{
    public function __construct(private QuestionnaireFieldTypeRegistry $registry)
    {
    }

    /**
     * Validates the settings array shape for a given field type.
     * Returns an array of human-readable error strings; empty array means OK.
     *
     * @return array<string>
     */
    public const SONG_PICKER_PURPOSES = ['must_play', 'do_not_play', 'general'];

    public function validate(string $type, ?array $settings): array
    {
        if (!$this->registry->isKnownType($type)) {
            return ["Unknown field type: {$type}"];
        }

        $required = $this->registry->definition($type)['required_settings'] ?? [];

        if (in_array('options', $required, true)) {
            return $this->validateOptions($settings);
        }

        if (in_array('purpose', $required, true)) {
            return $this->validatePurpose($settings);
        }

        return [];
    }

    private function validatePurpose(?array $settings): array
    {
        if (!is_array($settings) || !isset($settings['purpose']) || !is_string($settings['purpose'])) {
            return ['settings.purpose is required and must be a string'];
        }

        if (!in_array($settings['purpose'], self::SONG_PICKER_PURPOSES, true)) {
            return ['settings.purpose must be one of: ' . implode(', ', self::SONG_PICKER_PURPOSES)];
        }

        return [];
    }

    private function validateOptions(?array $settings): array
    {
        if (!is_array($settings) || !isset($settings['options']) || !is_array($settings['options'])) {
            return ['settings.options is required and must be an array'];
        }

        $options = $settings['options'];
        if (count($options) < 1) {
            return ['settings.options must contain at least one entry'];
        }

        $errors = [];
        foreach ($options as $i => $option) {
            if (!is_array($option)) {
                $errors[] = "settings.options[{$i}] must be an object with value and label";
                continue;
            }
            if (!isset($option['value']) || !is_string($option['value']) || $option['value'] === '') {
                $errors[] = "settings.options[{$i}].value is required and must be a non-empty string";
            }
            if (!isset($option['label']) || !is_string($option['label']) || $option['label'] === '') {
                $errors[] = "settings.options[{$i}].label is required and must be a non-empty string";
            }
        }
        return $errors;
    }
}
