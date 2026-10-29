<?php

namespace App\Services;

use InvalidArgumentException;

class QuestionnaireMappingRegistry
{
    public const TYPE_BOOLEAN_PATH = 'boolean_path';
    public const TYPE_DANCE_ENTRY = 'dance_entry';
    /** Append the answer as a line to a string path; an identical line is not added twice. */
    public const TYPE_TEXT_APPEND = 'text_append';
    /** Overwrite a string path with the answer. */
    public const TYPE_TEXT_SET = 'text_set';

    /** Review-screen sections, in display order. */
    public const GROUPS = [
        'timeline' => 'Timeline',
        'performance' => 'Performance',
        'setlist' => 'Setlist',
        'details' => 'Details',
        'contacts' => 'Contacts',
        'notes' => 'Notes',
    ];

    private const TEXT_TYPES = ['short_text', 'long_text'];

    /** Every input type whose answer reads sensibly as a line of text. */
    private const TEXT_AND_CHOICE_TYPES = [
        'short_text', 'long_text', 'email', 'phone', 'date', 'time',
        'dropdown', 'multi_select', 'checkbox_group', 'yes_no',
    ];

    /**
     * @return array<string, array{
     *     label: string,
     *     compatible_field_types: array<string>,
     *     kind: string,
     *     group: string,
     *     short_label: string,
     *     event_path?: array<string>,
     *     dance_title?: string,
     *     prefix_label?: bool,
     * }>
     */
    private function targets(): array
    {
        return [
            'wedding.onsite' => [
                'label' => 'Wedding · Onsite Ceremony',
                'short_label' => 'Onsite ceremony',
                'group' => 'details',
                'compatible_field_types' => ['yes_no'],
                'kind' => self::TYPE_BOOLEAN_PATH,
                'event_path' => ['additional_data', 'wedding', 'onsite'],
            ],
            'wedding.outside' => [
                'label' => 'Event · Outside Event',
                'short_label' => 'Outside event',
                'group' => 'details',
                'compatible_field_types' => ['yes_no'],
                'kind' => self::TYPE_BOOLEAN_PATH,
                'event_path' => ['additional_data', 'outside'],
            ],
            'wedding.dance.first' => [
                'label' => 'Wedding · First Dance',
                'short_label' => 'First dance',
                'group' => 'timeline',
                'compatible_field_types' => self::TEXT_TYPES,
                'kind' => self::TYPE_DANCE_ENTRY,
                'dance_title' => 'First Dance',
            ],
            'wedding.dance.father_daughter' => [
                'label' => 'Wedding · Father-Daughter Dance',
                'short_label' => 'Father-daughter dance',
                'group' => 'timeline',
                'compatible_field_types' => self::TEXT_TYPES,
                'kind' => self::TYPE_DANCE_ENTRY,
                'dance_title' => 'Father Daughter',
            ],
            'wedding.dance.mother_son' => [
                'label' => 'Wedding · Mother-Son Dance',
                'short_label' => 'Mother-son dance',
                'group' => 'timeline',
                'compatible_field_types' => self::TEXT_TYPES,
                'kind' => self::TYPE_DANCE_ENTRY,
                'dance_title' => 'Mother Son',
            ],
            'wedding.dance.money' => [
                'label' => 'Wedding · Money Dance',
                'short_label' => 'Money dance',
                'group' => 'timeline',
                'compatible_field_types' => self::TEXT_TYPES,
                'kind' => self::TYPE_DANCE_ENTRY,
                'dance_title' => 'Money Dance',
            ],
            'wedding.dance.bouquet_garter' => [
                'label' => 'Wedding · Bouquet/Garter',
                'short_label' => 'Bouquet/garter',
                'group' => 'timeline',
                'compatible_field_types' => self::TEXT_TYPES,
                'kind' => self::TYPE_DANCE_ENTRY,
                'dance_title' => 'Bouquet/Garter',
            ],
            'wedding.toasts' => [
                'label' => 'Wedding · Toasts',
                'short_label' => 'Toasts',
                'group' => 'timeline',
                'compatible_field_types' => self::TEXT_TYPES,
                'kind' => self::TYPE_DANCE_ENTRY,
                'dance_title' => 'Toasts',
            ],
            'wedding.colors' => [
                'label' => 'Wedding · Colors',
                'short_label' => 'Wedding colors',
                'group' => 'details',
                'compatible_field_types' => [...self::TEXT_TYPES, 'dropdown', 'multi_select', 'checkbox_group'],
                'kind' => self::TYPE_TEXT_SET,
                'event_path' => ['additional_data', 'wedding', 'colors'],
            ],
            'performance.notes' => [
                'label' => 'Performance · Notes',
                'short_label' => 'Performance notes',
                'group' => 'performance',
                'compatible_field_types' => self::TEXT_AND_CHOICE_TYPES,
                'kind' => self::TYPE_TEXT_APPEND,
                'event_path' => ['additional_data', 'performance', 'notes'],
            ],
            // Explicit opt-in only: never a preset default, so answers stop
            // landing in the general notes by default.
            'event.notes' => [
                'label' => 'Event · Notes',
                'short_label' => 'Event notes',
                'group' => 'notes',
                'compatible_field_types' => self::TEXT_AND_CHOICE_TYPES,
                'kind' => self::TYPE_TEXT_APPEND,
                'event_path' => ['notes'],
                'prefix_label' => true,
            ],
        ];
    }

    /**
     * @return array<string>
     */
    public function keys(): array
    {
        return array_keys($this->targets());
    }

    public function targetExists(string $key): bool
    {
        return array_key_exists($key, $this->targets());
    }

    /**
     * @return array<string>
     */
    public function compatibleFieldTypes(string $key): array
    {
        $this->assertTargetExists($key);
        return $this->targets()[$key]['compatible_field_types'];
    }

    public function label(string $key): string
    {
        $this->assertTargetExists($key);
        return $this->targets()[$key]['label'];
    }

    public function kind(string $key): string
    {
        $this->assertTargetExists($key);
        return $this->targets()[$key]['kind'];
    }

    /**
     * @return array<string>
     */
    public function eventPath(string $key): array
    {
        $this->assertTargetExists($key);
        return $this->targets()[$key]['event_path'] ?? [];
    }

    public function danceTitle(string $key): ?string
    {
        if (!$this->targetExists($key)) {
            return null;
        }
        return $this->targets()[$key]['dance_title'] ?? null;
    }

    /**
     * Short, sentence-case name for the review screen ("First dance").
     */
    public function shortLabel(string $key): string
    {
        $this->assertTargetExists($key);
        return $this->targets()[$key]['short_label'];
    }

    public function group(string $key): string
    {
        $this->assertTargetExists($key);
        return $this->targets()[$key]['group'];
    }

    public static function groupLabel(string $group): string
    {
        return self::GROUPS[$group] ?? ucfirst($group);
    }

    /**
     * Whether text written to this target is prefixed with the field label
     * ("Venue Name: Il Mercato"), for targets that mix many answers.
     */
    public function prefixesLabel(string $key): bool
    {
        $this->assertTargetExists($key);
        return (bool) ($this->targets()[$key]['prefix_label'] ?? false);
    }

    public function isCompatible(string $key, ?string $fieldType): bool
    {
        return $this->targetExists($key)
            && $fieldType !== null
            && in_array($fieldType, $this->targets()[$key]['compatible_field_types'], true);
    }

    /**
     * @return array<string> target keys that accept the given field type
     */
    public function compatibleTargetsFor(?string $fieldType): array
    {
        return array_values(array_filter(
            $this->keys(),
            fn ($key) => $this->isCompatible($key, $fieldType),
        ));
    }

    /**
     * Catalog suitable for sending to the builder UX. Each entry includes
     * a key, label, and compatible_field_types so the dropdown can filter
     * by selected field type.
     *
     * @return array<int, array{key: string, label: string, compatible_field_types: array<string>, short_label: string, group: string}>
     */
    public function catalog(): array
    {
        return collect($this->targets())->map(fn ($t, $key) => [
            'key' => $key,
            'label' => $t['label'],
            'compatible_field_types' => $t['compatible_field_types'],
            'short_label' => $t['short_label'],
            'group' => $t['group'],
        ])->values()->all();
    }

    private function assertTargetExists(string $key): void
    {
        if (!$this->targetExists($key)) {
            throw new InvalidArgumentException("Unknown mapping target: {$key}");
        }
    }
}
