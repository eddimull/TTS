<?php

namespace App\Services;

class QuestionnairePresetRegistry
{
    /**
     * Curated starter templates a band can clone when creating a new
     * questionnaire. Each preset's fields are deep-copied to a new
     * Questionnaires row on selection; bands then edit it like any other
     * template.
     *
     * @return array<string, array{
     *     name: string,
     *     description: string,
     *     fields: array<int, array{
     *         type: string,
     *         label: string,
     *         help_text?: string|null,
     *         required?: bool,
     *         settings?: array|null,
     *         mapping_target?: string|null,
     *     }>,
     * }>
     */
    private function presets(): array
    {
        return [
            'wedding' => [
                'name' => 'Wedding Day Questionnaire',
                'description' => 'Standard wedding-reception questionnaire covering wedding party details, '
                    . 'first dances, money/bouquet dances, color scheme, and production notes.',
                'fields' => [
                    ['type' => 'header', 'label' => 'Bride and Groom Information'],

                    ['type' => 'short_text', 'label' => "Bride's Full Name", 'help_text' => 'Including spelling for the MC', 'required' => true],
                    ['type' => 'short_text', 'label' => "Groom's Full Name", 'help_text' => 'Including spelling for the MC', 'required' => true],
                    ['type' => 'date', 'label' => 'Wedding Date', 'required' => true],
                    ['type' => 'short_text', 'label' => 'Venue Name', 'required' => true],
                    ['type' => 'long_text', 'label' => 'Venue Address (street, city, state, zip)', 'required' => true],
                    ['type' => 'email', 'label' => 'Best contact email', 'required' => true],
                    ['type' => 'phone', 'label' => 'Best contact phone', 'required' => true],
                    ['type' => 'yes_no', 'label' => 'Will the ceremony be onsite?', 'mapping_target' => 'wedding.onsite'],
                    ['type' => 'yes_no', 'label' => 'Is any portion of the event outdoors?', 'mapping_target' => 'wedding.outside'],

                    ['type' => 'header', 'label' => 'First Dances'],
                    ['type' => 'instructions', 'label' => 'Title and artist for each. Leave blank if you do not want a particular dance.'],
                    ['type' => 'short_text', 'label' => 'First Dance', 'mapping_target' => 'wedding.dance.first'],
                    ['type' => 'short_text', 'label' => 'Father-Daughter Dance', 'mapping_target' => 'wedding.dance.father_daughter'],
                    ['type' => 'short_text', 'label' => 'Mother-Son Dance', 'mapping_target' => 'wedding.dance.mother_son'],

                    ['type' => 'header', 'label' => 'Bouquet, Money Dance, and Toasts'],
                    ['type' => 'short_text', 'label' => 'Money Dance song', 'mapping_target' => 'wedding.dance.money'],
                    ['type' => 'short_text', 'label' => 'Bouquet/Garter song', 'mapping_target' => 'wedding.dance.bouquet_garter'],
                    ['type' => 'long_text', 'label' => 'Names and order of toasts', 'help_text' => 'Who is giving toasts and in what order?'],

                    ['type' => 'header', 'label' => 'Music Preferences'],
                    ['type' => 'long_text', 'label' => 'Must-play songs / artists', 'help_text' => 'Songs you absolutely want to hear'],
                    ['type' => 'long_text', 'label' => 'Do-not-play songs / artists', 'help_text' => 'Songs we should avoid'],

                    ['type' => 'header', 'label' => 'Color Scheme'],
                    ['type' => 'long_text', 'label' => 'Wedding colors', 'help_text' => 'Helpful for the band\'s attire choice'],

                    ['type' => 'header', 'label' => 'Production Notes'],
                    ['type' => 'long_text', 'label' => 'Anything else we should know?'],
                ],
            ],
        ];
    }

    /**
     * Lightweight catalog for the New Questionnaire dialog.
     *
     * @return array<int, array{key: string, name: string, description: string, field_count: int}>
     */
    public function catalog(): array
    {
        return collect($this->presets())->map(fn ($preset, $key) => [
            'key' => $key,
            'name' => $preset['name'],
            'description' => $preset['description'],
            'field_count' => count($preset['fields']),
        ])->values()->all();
    }

    public function exists(string $key): bool
    {
        return array_key_exists($key, $this->presets());
    }

    /**
     * @return array{name: string, description: string, fields: array}
     */
    public function get(string $key): array
    {
        if (!$this->exists($key)) {
            throw new \InvalidArgumentException("Unknown preset: {$key}");
        }
        return $this->presets()[$key];
    }
}
