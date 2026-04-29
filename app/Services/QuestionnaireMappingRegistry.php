<?php

namespace App\Services;

use InvalidArgumentException;

class QuestionnaireMappingRegistry
{
    public const TYPE_BOOLEAN_PATH = 'boolean_path';
    public const TYPE_DANCE_ENTRY = 'dance_entry';

    /**
     * @return array<string, array{
     *     label: string,
     *     compatible_field_types: array<string>,
     *     kind: string,
     *     event_path?: array<string>,
     *     dance_title?: string,
     * }>
     */
    private function targets(): array
    {
        return [
            'wedding.onsite' => [
                'label' => 'Wedding · Onsite Ceremony',
                'compatible_field_types' => ['yes_no'],
                'kind' => self::TYPE_BOOLEAN_PATH,
                'event_path' => ['additional_data', 'wedding', 'onsite'],
            ],
            'wedding.outside' => [
                'label' => 'Event · Outside Event',
                'compatible_field_types' => ['yes_no'],
                'kind' => self::TYPE_BOOLEAN_PATH,
                'event_path' => ['additional_data', 'outside'],
            ],
            'wedding.dance.first' => [
                'label' => 'Wedding · First Dance',
                'compatible_field_types' => ['short_text'],
                'kind' => self::TYPE_DANCE_ENTRY,
                'dance_title' => 'First Dance',
            ],
            'wedding.dance.father_daughter' => [
                'label' => 'Wedding · Father-Daughter Dance',
                'compatible_field_types' => ['short_text'],
                'kind' => self::TYPE_DANCE_ENTRY,
                'dance_title' => 'Father Daughter',
            ],
            'wedding.dance.mother_son' => [
                'label' => 'Wedding · Mother-Son Dance',
                'compatible_field_types' => ['short_text'],
                'kind' => self::TYPE_DANCE_ENTRY,
                'dance_title' => 'Mother Son',
            ],
            'wedding.dance.money' => [
                'label' => 'Wedding · Money Dance',
                'compatible_field_types' => ['short_text'],
                'kind' => self::TYPE_DANCE_ENTRY,
                'dance_title' => 'Money Dance',
            ],
            'wedding.dance.bouquet_garter' => [
                'label' => 'Wedding · Bouquet/Garter',
                'compatible_field_types' => ['short_text'],
                'kind' => self::TYPE_DANCE_ENTRY,
                'dance_title' => 'Bouquet/Garter',
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
     * Catalog suitable for sending to the builder UX. Each entry includes
     * a key, label, and compatible_field_types so the dropdown can filter
     * by selected field type.
     *
     * @return array<int, array{key: string, label: string, compatible_field_types: array<string>}>
     */
    public function catalog(): array
    {
        return collect($this->targets())->map(fn ($t, $key) => [
            'key' => $key,
            'label' => $t['label'],
            'compatible_field_types' => $t['compatible_field_types'],
        ])->values()->all();
    }

    private function assertTargetExists(string $key): void
    {
        if (!$this->targetExists($key)) {
            throw new InvalidArgumentException("Unknown mapping target: {$key}");
        }
    }
}
