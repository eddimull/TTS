<?php

namespace App\Services;

use InvalidArgumentException;

class QuestionnaireFieldTypeRegistry
{
    /**
     * @return array<string, array{label: string, is_input: bool, required_settings?: array<string>}>
     */
    private function definitions(): array
    {
        return [
            'short_text'     => ['label' => 'Short text',      'is_input' => true],
            'long_text'      => ['label' => 'Long text',       'is_input' => true],
            'date'           => ['label' => 'Date',            'is_input' => true],
            'time'           => ['label' => 'Time',            'is_input' => true],
            'email'          => ['label' => 'Email',           'is_input' => true],
            'phone'          => ['label' => 'Phone',           'is_input' => true],
            'dropdown'       => ['label' => 'Dropdown',        'is_input' => true, 'required_settings' => ['options']],
            'multi_select'   => ['label' => 'Multi-select',    'is_input' => true, 'required_settings' => ['options']],
            'checkbox_group' => ['label' => 'Checkboxes',      'is_input' => true, 'required_settings' => ['options']],
            'yes_no'         => ['label' => 'Yes / No',        'is_input' => true],
            'song_picker'    => ['label' => 'Song picker',     'is_input' => true, 'required_settings' => ['purpose']],
            'header'         => ['label' => 'Section header',  'is_input' => false],
            'instructions'   => ['label' => 'Instruction text', 'is_input' => false],
        ];
    }

    /**
     * @return array<string>
     */
    public function knownTypes(): array
    {
        return array_keys($this->definitions());
    }

    public function isKnownType(string $type): bool
    {
        return array_key_exists($type, $this->definitions());
    }

    public function isInputType(string $type): bool
    {
        if (!$this->isKnownType($type)) {
            return false;
        }
        return (bool) $this->definitions()[$type]['is_input'];
    }

    /**
     * @return array{label: string, is_input: bool, required_settings?: array<string>}
     */
    public function definition(string $type): array
    {
        if (!$this->isKnownType($type)) {
            throw new InvalidArgumentException("Unknown field type: {$type}");
        }
        return $this->definitions()[$type];
    }

    /**
     * Returns the full type catalog suitable for shipping to the Vue layer
     * (used by Vuex on builder mount).
     *
     * @return array<int, array{type: string, label: string, is_input: bool, required_settings: array<string>}>
     */
    public function catalog(): array
    {
        return collect($this->definitions())->map(fn ($def, $type) => [
            'type' => $type,
            'label' => $def['label'],
            'is_input' => $def['is_input'],
            'required_settings' => $def['required_settings'] ?? [],
        ])->values()->all();
    }
}
